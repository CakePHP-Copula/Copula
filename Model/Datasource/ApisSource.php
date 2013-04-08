<?php

/**
 * Apis DataSource
 *
 * [Short Description]
 *
 * @package default
 * @author Dean Sofer
 * */
App::uses('DataSource', 'Model/Datasource');
App::uses('HttpSocketOauth', 'Copula.Network/Http');
App::uses('HttpSocket', 'Network/Http');
App::uses('Hash', 'Utility');

class ApisSource extends DataSource {

/**
 * The description of this data source
 *
 * @var string
 */
	public $description = 'Apis DataSource';

/**
 * Holds the datasource configuration
 *
 * @var array
 */
	public $config = array();

/**
 * Request Logs
 *
 * @var array
 * @access protected
 */
	protected $_requestLog = array();

/**
 * Request Log limit per entry in bytes
 *
 * @var integer
 * @access protected
 */
	protected $_logLimitBytes = 5000;

/**
 * Holds a configuration map
 *
 * @var array
 */
	public $map = array();

	protected $_sources = null;

	protected $_baseConfig = array(
		'format' => 'json',
		'escape' => 'false',
		'authMethod' => 'OAuthV2'
	);

/**
 * Stores mapping of db actions to http methods
 * @var type
 */
	public $restMap = array(
		'create' => 'POST',
		'read' => 'GET',
		'update' => 'PUT',
		'delete' => 'DELETE'
	);

/**
 * Returns the appropriate transport object for the datasource.
 *
 * @param string $authMethod 'OAuth' or 'OAuthV2' return HttpSocketOauth object
 * @param string|array $url url or array of config options
 * @return \HttpSocketOauth|\HttpSocket
 */
	public function getHttpObject($authMethod, $url = null) {
		switch ($authMethod) {
			case 'OAuth':
			case 'OAuthV2':
				$Http = new HttpSocketOauth($url);
				break;
			default:
				$Http = new HttpSocket($url);
				break;
		}
		return $Http;
	}

/**
 *
 * @param \Model $model
 * @return mixed
 */
	public function describe(\Model $model) {
		if (!empty($model->schema)) {
			$schema = $model->schema;
		} elseif (!empty($this->_schema[$model->name])) {
			$schema = $this->_schema[$model->name];
		} elseif (!empty($this->map[$model->useDbConfig])) {
			$schema = $this->map[$model->useDbConfig];
		} else {
			return null;
		}
		return $schema;
	}

/**
 *
 * @param string|array $query array of params to be converted
 * @param boolean $escape whether to escape the separator
 * @return string string containing query
 */
	protected function _buildQuery($query, $escape = false) {
		if (is_array($query)) {
			$query = substr(Router::queryString($query, array(), $escape), '1');
		}
		return $query;
	}

/**
 * In an ideal world, this would construct a HttpRequest object. Such will likely be the case in CakePHP 3.0
 *
 * Currently it returns an array of values to be passed to HttpSocket.
 *
 * @param string $apiName
 * @param string $type
 * @param array  $request
 * @return array
 */
	protected function _buildRequest($apiName, $type = 'read', $request = array()) {
		if (empty($this->map)) {
			$this->map = Configure::read("Copula.$apiName.path");
		}
		$host = Configure::read("Copula.$apiName.Api");
		if (is_array($host)) {
			$this->setConfig($host);
		}
		$request['method'] = $this->restMap[$type];
		$request['uri']['host'] = $this->config['host'];
		$request['auth'] = $this->_getAuth($this->config['authMethod'], $apiName);
		if (!empty($this->config['scheme'])) {
			$request['uri']['scheme'] = $this->config['scheme'];
		}
		return $request;
	}

/**
 *
 * @param type $data
 * @return null
 */
	public function listSources($data = null) {
		if (!empty($this->map)) {
			foreach ($this->map as $section) {
				foreach ($section as $endpoint => $contents) {
					$endpoints[] = $endpoint;
				}
			}
			return array_unique($endpoints);
		} else {
			return null;
		}
	}

/**
 * Sends HttpSocket requests. Builds your uri and formats the response too.
 *
 * @param string $params
 * @param array $options
 * 		method: get, post, delete, put
 * 		data: either in string form: "option1=foo&option2=bar" or as a keyed array: array('option1' => 'foo', 'option2' => 'bar')
 * @return HttpSocketResponse $response
 * @author Dean Sofer
 */
	public function request(Model $model) {
		if (!empty($this->tokens)) {
			$model->request['uri']['path'] = $this->_swapTokens($model->request['uri']['path'], $this->tokens);
		}

		if (method_exists($this, 'beforeRequest')) {
			$model->request = $this->beforeRequest($model);
		}

		$Http = $this->getHttpObject($this->config['authMethod']);
		$t = microtime(true);

		$Http->request($model->request);

		$this->took = round((microtime(true) - $t) * 1000, 0);
		$this->logQuery($Http);

		$model->response = $this->afterRequest($model, $Http->response);

		return $model->response;
	}

/**
 *
 * @param type $query
 * @param \HttpSocketResponse $response
 * @return void
 */
	public function logQuery(\HttpSocket $Socket) {
		if (Configure::read('debug')) {
			$logItems = array($Socket->request['raw'], $Socket->response->raw);
			foreach ($logItems as &$logPart) {
				if (strlen($logPart) > $this->_logLimitBytes) {
					$logPart = substr($logPart, 0, $this->_logLimitBytes) . ' [ ... truncated ...]';
				}
			}
			$newLog = array(
				'query' => $logItems[0],
				'response' => $logItems[1],
				'took' => $this->took,
			);
			$this->_requestLog[] = $newLog;
		}
	}

/**
 *
 * @param type $method
 * @return array
 */
	protected function _getAuth($method, $apiName) {
		switch ($method) {
			case 'Basic':

				$auth = array(
					'method' => 'Basic',
					'login' => $this->config['login'],
					'password' => $this->config['password']
				);
				break;
			case 'OAuth':
				$auth = array(
					'method' => 'OAuth',
					'oauth_consumer_key' => $this->config['login'],
					'oauth_consumer_secret' => $this->config['password']
						,);
				$auth['oauth_token'] = (!empty($this->config['access_token'])) ? $this->config['access_token'] : null;
				$auth['oauth_token_secret'] = (!empty($this->config['token_secret'])) ? $this->config['token_secret'] : null;
				break;
			case 'OAuthV2':
				$auth = array(
					'method' => 'OAuth',
					'oauth_version' => '2.0',
					'client_id' => $this->config['login'],
					'client_secret' => $this->config['password']
				);
				$auth['access_token'] = (isset($this->config['access_token'])) ? $this->config['access_token'] : null;
				break;
			default:
				$auth = null;
				break;
		}
		return array_filter($auth);
	}

/**
 * Decodes the response based on the content type
 *
 * @param \HttpSocketResponse $response
 * @return array $response
 * @author Dean Sofer
 */
	public function decode(\HttpSocketResponse $response) {
		// Get content type header
		$contentType = explode(';', $response->getHeader('Content-Type'));

		// Decode response according to content type
		switch ($contentType[0]) {
			case 'application/xml':
			case 'application/atom+xml':
			case 'application/rss+xml':
				App::uses('Xml', 'Utility');
				$Xml = Xml::build($response->body());
				$return = Xml::toArray($Xml);
				//one of the two lines of code following is unecessary.
				//Unset will delete the reference and mark the memory for garbage collection.
				//setting null will clear the memory immediately.
				//There is some overhead involved in shrinking the stack, so if you are calling this repeatedly it may not be faster.
				$Xml = null;
				unset($Xml);
				break;
			case 'application/json':
			case 'application/javascript':
			case 'text/javascript':
				$return = json_decode($response->body(), true);
				break;
			default:
				$return = $response->body();
				break;
		}
		return $return;
	}

/**
 * Iterates through the tokens (passed or request items) and replaces them into the url
 *
 * @param string $url
 * @param array $tokens optional
 * @return string $url
 * @author Dean Sofer
 */
	protected function _swapTokens($url, $tokens = array()) {
		$formattedTokens = array();
		foreach ($tokens as $token => $value) {
			$formattedTokens[':' . $token] = $value;
		}
		$url = strtr($url, $formattedTokens);
		return $url;
	}

/**
 * Tries iterating through the config map of REST commmands to decide which command to use
 *
 * @param string $action
 * @param string $section
 * @param array $fields
 * @return array
 * @throws CakeException if match not found
 * @throws CakeException if section not found
 */
	protected function _scanMap($action, $section, $fields = array()) {
		$map = $this->map[$action];
		if (!Hash::check($map, $section)) {
			throw new CakeException(__('Section %s not found in Copula Driver Configuration Map - ', $section) . get_class($this), 500);
		} else {
			$element = Hash::extract($map, $section);
			$path = $required = $optional = null;
			extract($element);
			if (array_intersect($fields, $required) == $required) {
				return compact('path', 'required', 'optional');
			}
			throw new CakeException(__('[ApiSource] Could not find a match for passed conditions'), 500);
		}
	}

/**
 * Play nice with the DebugKit
 *
 * @param boolean sorted ignored
 * @param boolean clear will clear the log if set to true (default)
 * @return array of log requested
 */
	public function getLog($sorted = false, $clear = true) {
		$log = $this->_requestLog;
		if ($clear) {
			$this->_requestLog = array();
		}
		return array('log' => $log, 'count' => count($log), 'time' => 'Unknown');
	}

/**
 * Just-In-Time callback for any last-minute request modifications
 *
 * @param Model $model
 * @return array
 * @author Dean Sofer
 */
	public function beforeRequest(Model $model) {
		return $model->request;
	}

	public function afterRequest(Model &$model, HttpSocketResponse &$response) {
		if (!$response->isOk()) {
			$model->onError();
			return false;
		} else {
			return $this->decode($response);
		}
	}

/**
 * Uses standard find conditions. Use find('all', $params).
 *
 * @param string $model The model being read.
 * @param string $queryData An array of query data used to find the data you want
 * @return mixed
 * @access public
 */
	public function read(Model $model, $queryData = array()) {
		if (!empty($queryData['fields']) && $queryData['fields'] == 'COUNT') {
			return array(array(array('count' => 1)));
		}
		$queryData['conditions'] = (isset($queryData['conditions'])) ? $queryData['conditions'] : array();
		$model->request = $this->_buildRequest($model->useDbConfig, 'read');
		$scan = $this->_scanMap('read', $model->useTable, array_keys($queryData['conditions']));
		$required = $optional = array();
		extract($scan);
		$model->request['uri']['path'] = $path;
		$conditions = array_intersect_key($queryData['conditions'], array_flip(array_merge($required, $optional)));
		$model->request['uri']['query'] = $this->_buildQuery($conditions);
		return $this->request($model);
	}

/**
 * Sets method = POST in request if not already set
 *
 * @param AppModel $model
 * @param array $fields Unused
 * @param array $values Unused
 */
	public function create(Model $model, $fields = null, $values = null) {
		$model->request = $this->_buildRequest($model->useDbConfig, 'create');
		$scan = $this->_scanMap('create', $model->useTable, $fields);
		extract($scan);
		$model->request['uri']['path'] = $path;
		$model->request['body'] = $this->_buildQuery(array_combine($fields, $values), $this->config['escape']);
		return $this->request($model);
	}

/**
 * Sets method = PUT in request if not already set
 *
 * @param AppModel $model
 * @param array $fields Unused
 * @param array $values Unused
 */
	public function update(Model $model, $fields = null, $values = null, $conditions = null) {
		$model->request = $this->_buildRequest($model->useDbConfig, 'update');
		$scan = $this->_scanMap('update', $model->useTable, $fields);
		extract($scan);
		$model->request['uri']['path'] = $path;
		$model->request['body'] = $this->_buildQuery(array_combine($fields, $values), $this->config['escape']);
		return $this->request($model);
	}

/**
 * Sets method = DELETE in request if not already set
 *
 * @param AppModel $model
 * @param mixed $id Unused
 */
	public function delete(Model $model, $conditions = null) {
		$model->request = $this->_buildRequest($model->useDbConfig, 'delete');
		$scan = $this->_scanMap('delete', $model->useTable, array_keys($conditions));
		extract($scan);
		$model->request['uri']['path'] = $path;
		$model->request['body'] = $this->_buildQuery($conditions, $this->config['escape']);
		return $this->request($model);
	}

	public function calculate($model, $func, $params = array()) {
		return 'COUNT';
	}

	public function getColumnType() {
		return true;
	}

}
