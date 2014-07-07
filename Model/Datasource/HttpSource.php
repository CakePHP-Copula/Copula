<?php

App::uses('DataSource', 'Model/Datasource');
App::uses('Request', 'Copula.Network/Http');
App::uses('Xml', 'Utility');
App::uses('HttpSocket', 'Network/Http');
App::uses('HttpSocketResponse', 'Network/Http');
App::uses('Token', 'Copula.Network/Http');
//App::uses('FileLib', 'Tools.Lib/Utility');

class HttpSource extends DataSource {

	/**
	 * The description of this data source
	 *
	 * @var string
	 */
	public $description = 'Remote Http-based Datasource';

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
	 */
	protected $_requestLog = array();

	/**
	 * Request Log limit per entry in bytes
	 *
	 * @var integer
	 */
	protected $_logLimitBytes = 5000;

	/**
	 *
	 * @var string Base URL string for requests
	 */
	public $baseUrl = '';

	/**
	 * Holds a configuration map
	 *
	 * @var array
	 */
	public $map = array(

		);

	/**
	 * Base Configuration for the datasource
	 *
	 * Adapter is the name of the class, with an array of config data
	 * @var array
	 */
	protected $_baseConfig = array(
		'authorize' => array(),
		'adapter' => array('HttpSocket' => array()),
		'requestObject' => 'Request',
		'decoder' => '',
		'escape' => false,
		'allowGetBody' => false
	);

	/**
	 * Stores mapping of db actions to http methods
	 *
	 * @var array
	 */
	public $restMap = array(
		'create' => 'POST',
		'read' => 'GET',
		'update' => 'PUT',
		'delete' => 'DELETE'
	);

	/**
	 * Provides fields used by an endpoint.
	 *
	 * @return array
	 */
	public function describe($model) {
		$keys = array();
		foreach ($this->map[$model->useTable] as $method) {
			$keys += array_keys($method);
		}
		return $keys;
	}

	/**
	 * Required by CakePHP
	 *
	 * @return array
	 */
	public function listSources($data = null) {
		return array_keys($this->map);
	}

	/**
	 * Required by CakePHP, impossible to implement generally, so we fake it
	 *
	 * @return string
	 */
	public function calculate($model, $func, $params = array()) {
		return 'COUNT';
	}


	/**
	 * This function contains the procedures used to make remote API requests
	 *
	 * @param Model the endpoint
	 * @param The method (HTTP or SOAP) used to fetch data
	 * @param array $queryData Data to be included in the querystring, if any
	 * @param array $postData Data to be used in the body of the request
	 * @return array Generally returns an array of data
	 * @throws CakeException when authorization cannot be obtained
	 * @throws CakeException If request fails validation
	 */
	public function request(Model $model, $method, array $queryData = array(), array $postData = array()) {
		if (!$this->isAuthorized($model, $queryData, $postData)) {
			throw new CakeException('Unauthorized Request from ' . $model->alias);
		}
		$endpoint = $model->useTable;
		if (!isset($this->map[$endpoint][$method])) {
			throw new NotFoundException("Method $method not found at $endpoint");
		}
		//transport layer is first
		//reason:seems appropriate

		$client = $this->_getTransport($endpoint);
		// build the request
		// the url should be an array after this point, and merged with the baseurl
		$request = $this->buildRequest($method, $endpoint, $queryData, $postData);
		//validate the request
		if (!empty($this->map[$endpoint][$method])) {
			$validationRules = $this->map[$endpoint][$method];
			if (!$request->isValid($validationRules)) {
				throw new CakeException('Invalid request.');
			}
		}

		//beforeRequest event
		$beforeEvent = new CakeEvent('Datasource.HttpSource.beforeRequest', $this, array('request' => $request));
		$model->getEventManager()->dispatch($beforeEvent);
		if (!empty($beforeEvent->result)) {
			$request = $beforeEvent->result;
		}
		//start timer
		$this->start = microtime(true);
		//using a separate method to make testing easier
		$response = $this->send($client, $request);
		$this->took = round((microtime(true) - $this->start) * 1000, 0);

		//logging
		$this->logQuery($request, $response);
		//error handling
		if (!$response->isOK()) {
			//the question is whether an exception is appropriate, or an onError event, or whether to leave that configurable
			//using a separate method defers that question
			//returning early, however, is probably a good idea
			return $model->onError($client, $request, $response);
		}
		$afterEvent = new CakeEvent('Datasource.HttpSource.afterRequest', $this, array('request' => $request, 'response' => $response));
		$model->getEventManager()->dispatch($afterEvent);
		if (!empty($afterEvent->result)) {
			$response = $afterEvent->result;
		}

		return $this->decode($response);
	}

	/**
	 * HttpSource::isAuthorized()
	 * This allows or disallows requests for this API
	 *
	 * @param mixed $model
	 * @param mixed $queryData
	 * @param mixed $postData
	 * @return bool Success
	 */
	public function isAuthorized(Model $model, array $queryData, array $postData) {
		/*
		this is more or less written with the assumption that you will only have one authorization method for a given source
		the more sensible course would have been to ape the way AuthComponent does things
		more sensible still would be to generalize AuthComponent functions into a generic security layer and use it for ACL too
		or at least to not duplicate the functionality and use whatever extant AuthComponent methods there are.
		The sensible option is therefore out of scope, and so this code will do something simple and stupid, and if you need anything else, redefine the method in a child class
		*/
		$authData = $this->config['authorize'];
		if (!empty($authData) && $authData['type'] === 'Token') {
			if (!isset($this->Token)) {
				$this->Token = ClassRegistry::init($authData['class']);
				$this->Token->fetch();
			}
			if ($this->Token->isExpired()) {
				$this->Token->refresh();
				return !$this->Token->isExpired(); #rather than requiring Token::refresh() to return something specific, I guess
			}
		}
		return true;
	}

	/**
	 * This fetches the class used to make requests, by default HttpSocket
	 *
	 * @return Client
	 */
	protected function _getTransport($endpoint) {
		$adapter = $this->config['adapter'];
		$assoc = (bool)count(array_filter(array_keys($adapter), 'is_string'));
		if ($assoc) {
			//'string' => array
			$name = key($adapter);
			$options = $adapter[$name];
		} else {
			// 0 => 'string'
			$name = $adapter[0];
			$options = array();
		}
		//we assume that the adapter has been appropriately included
		return new $name($options);
	}

	/**
	 * This creates a HTTP request object
	 *
	 * @param string $method HTTP method of the request
	 * @param string $endpoint The path to the requested resource
	 * @param array $queryData Array containing querystring params
	 * @param array $postData Array containing data for the message body
	 * @return Request This returns an object representing
	 */
	public function buildRequest($method, $endpoint, array $queryData, array $postData) {
		$requestObject = (isset($this->config['requestObject'])) ? $this->config['requestObject'] : 'Request';
		$request = new $requestObject();
		$request->method($method);
		$request->body($postData);
		$request->header($this->_getHeader($method, $endpoint));
		$request->url($this->_getUrl($endpoint, $queryData));
		return $request;
	}

	/**
	 * This constructs any parameters to pass in the HTTP header
	 * @param string $method The HTTP or SOAP method
	 * @param string $endpoint The path to be requested
	 * @return array An array of header parameters
	 */
	protected function _getHeader($method, $endpoint) {
		$header = array('Accept-Charset' => 'utf8');
		if (!empty($this->config['authorize'])) {
			$auth = $this->_getAuth($this->config['authorize']);
			$header += compact('auth');
		}
		return $header;
	}

	/**
	 * This constructs the path and querystring for the HTTP/SOAP request
	 *
	 * @param string $endpoint The path to be requested
	 * @param array $queryData Any querystring parameters
	 * @return array A URI array
	 */
	protected function _getUrl($endpoint, array $queryData) {
		$uri = parse_url($this->baseUrl);
		$uri['path'] = $endpoint;
		$uri['query'] = (isset($queryData['conditions'])) ? $queryData['conditions'] : array();
		return $uri;
	}

	/**
	 * This constructs header values for various authorization schemes, namely HTTP Basic, OAuthV1, and OAuthV2
	 *
	 * @param string $method The type of authorization to be used
	 * @return array An array of header/authorization values
	 */
	protected function _getAuth($method) {
		switch ($method) {
			case 'Basic':
				$auth = array(
					'method' => 'Basic',
					'user' => $this->config['login'],
					'pass' => $this->config['password']
				);
				break;
			case 'OAuth':
				$auth = array(
					'method' => 'OAuth',
					'oauth_consumer_key' => $this->config['login'],
					'oauth_consumer_secret' => $this->config['password']
						);
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
				$auth = array();
				break;
		}
		return array_filter($auth);
	}

	/**
	 * HttpSource::validateRequest()
	 * This validates either querystring parameters or request body parameters using AppModel and the validation array from $this->map
	 *
	 * @param string $method The HTTP or SOAP method
	 * @param Request $request The Request object
	 * @param array $queryData An array of querystring data
	 * @param array $postData An array of POST or request body data
	 * @return bool Success Whether the request is valid
	 */
	public function validateRequest($method, $request, $queryData, $postData) {
		$url = $request->url();
		if (isset($this->map[$url['path']][$method])) {
			$validationRules = $this->map[$url['path']][$method];
		} else {
			throw new CakeException("Method $method not found at " . $url['path']);
		}
		//If the query is GET, assume all params are querystring
		//Otherwise assume they are all body params
		if ($request->method() === 'GET') {
			$fieldsToValidate = $url['query'];
		} else {
			$fieldsToValidate = $request->body();
		}
		//this strategy seems better than using the real model
		$ValidationModel = ClassRegistry::init('AppModel');
		$ValidationModel->useTable = false;
		$ValidationModel->create();
		$ValidationModel->validate = $validationRules;
		$ValidationModel->set(array('AppModel' => $fieldsToValidate));
		$ValidationModel->validates();
		if (!empty($ValidationModel->validationErrors) && Configure::read('debug')) {
			debug($ValidationModel->validationErrors);
		}
		return empty($ValidationModel->validationErrors);
	}

	/**
	 * HttpSource::send()
	 * This function does the actual data retrieval, SOAP requests will probably override this
	 *
	 * @param Client $transport
	 * @param Request $request
	 * @return HttpSocketResponse
	 */
	public function send($transport, $request) {
		$requestArray = array();
		$requestArray['uri'] = $request->url();
		$requestArray['body'] = $request->body();
		$requestArray['method'] = $request->method();
		try {
			$response = $transport->request($requestArray);
		} catch (SocketException $e) {
			$response = new HttpSocketResponse();
			$response->code = 500;
			$response->body($e->getMessage());
		}
		return $response;
	}

	/**
	 * HttpSource::logQuery()
	 * This writes the requests and responses to a log if debug is enabled
	 *
	 * @param Request $request
	 * @param HttpSocketResponse $response
	 * @return void
	 */
	public function logQuery(Request $request, HttpSocketResponse $response) {
		if (Configure::read('debug')) {
			$logItems = array(json_encode($request), $response->raw);
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
	 * Decodes the response based on the content type
	 *
	 * @param \HttpSocketResponse $response
	 * @return array $response
	 */
	public function decode(\HttpSocketResponse $response) {
		// Get content type header
		$contentType = explode(';', $response->getHeader('Content-Type'));
		$message = $response->body();
		// if the decoder has already been set, use that
		$decoder = isset($this->config['decoder']) ? $this->config['decoder'] : null;
		if ($decoder && method_exists($this, '_decode' . $decoder)) {
			return $this->{'_decode' . $decoder}($message);
		}
		// Decode response according to content type
		switch ($contentType[0]) {
			case 'application/xml':
			case 'application/atom+xml':
			case 'application/rss+xml':
			case 'text/xml':
				return $this->_decodeXml($message);
				break;
			case 'application/json':
			case 'application/javascript':
			case 'text/javascript':
				return $this->_decodeJson($message);
				break;
			case 'text/csv':
				return $this->_decodeCsv($message);
				break;
			default:
				return $message;
				break;
		}
	}

	/**
	 * HttpSource::_decodeXml()
	 *
	 * @param mixed $message
	 * @return array
	 */
	protected function _decodeXml($message) {
		$Xml = Xml::build($message);
		return Xml::toArray($Xml);
	}

	/**
	 * HttpSource::_decodeJson()
	 *
	 * @param mixed $message
	 * @return array
	 */
	protected function _decodeJson($message) {
		return json_decode($message, true);
	}

	/**
	 * HttpSource::_decodeCsv()
	 *
	 * @param mixed $message
	 * @return array
	 */
	protected function _decodeCsv($message) {
		$handle = tmpfile();
		$meta = stream_get_meta_data($handle);
		$filename = $meta['uri'];
		fwrite($handle, $message);
		$File = new FileLib($filename);
		$csv = $File->readCsv(0, ';');
		unlink($filename);
		return $csv;
	}

	/**
	 * HttpSource::read()
	 * Defaults to GET
	 *
	 * @param Model $model
	 * @param array $queryData
	 * @param mixed $recursive
	 * @return array
	 */
	public function read(Model $model, $queryData = array(), $recursive = null) {
		if (!empty($queryData['fields']) && $queryData['fields'] == 'COUNT') {
			return array(array(array('count' => 1)));
		}
		if (!$this->config['allowGetBody'] && isset($queryData['_postData'])) {
			//this must be set by a custom finder. Yes, there are probably easier methods of doing this, but I'm not expecting anyone to
			$postData = $queryData['_postData'];
			unset($queryData['_postData']);
		} else {
			$postData = array();
		}
		return $this->request($model, $this->restMap['read'], $queryData, $postData);
	}

	/**
	 * HttpSource::create
	 * Defaults to POST
	 *
	 * @param Model $model
	 * @param array $fields
	 * @param array $values
	 * @return array
	 */
	public function create(Model $model, $fields = null, $values = null) {
		$postData = array_combine($fields, $values);
		return $this->request($model, $this->restMap['create'], array(), $postData);
	}

	/**
	 * HttpSource::update()
	 * Defaults to PUT. Probably will not be implemented by most APIs
	 * @param Model $model
	 * @param array $fields
	 * @param array $values
	 * @param array $conditions
	 * @return array
	 */
	public function update(Model $model, $fields = null, $values = null, $conditions = null) {
		return $this->request($model, $this->restMap['update'], $conditions, array_combine($fields, $values));
	}

	/**
	 * HttpSource::delete()
	 * Defaults to DELETE. This will probably never be supported by any API
	 * @param Model $model
	 * @param array $conditions
	 * @return array
	 */
	public function delete(Model $model, $conditions = null) {
		return $this->request($model, $this->restMap['delete'], compact('conditions'));
	}

	/**
	 * Methods not implemented by the model class will end up here. Unlike normal datasources, this behavior is actually useful here
	 *
	 * @param string $method The method called by the model
	 * @param array $params An array of parameters passed to the method
	 * @param Model $model The model object calling the method
	 * @return array The data returned by the remote API
	 */
	public function query($method, $params, $model) {
		$endpoint = $model->useTable;
		return $this->request($model, $method, array(), $params);
	}

}
