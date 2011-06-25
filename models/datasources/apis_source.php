<?php
/**
 * Apis DataSource
 * 
 * [Short Description]
 *
 * @package default
 * @author Dean Sofer
 **/
class ApisSource extends DataSource {

/**
 * The description of this data source
 *
 * @var string
 */
	public $description = 'Apis DataSource';

/**
 * Array containing the names of components this component uses. Component names
 * should not contain the "Component" portion of the classname.
 *
 * @var array
 * @access public
 */
	var $config = array();
	
	// TODO: Relocate to a dedicated schema file
	var $_schema = array();

/**
 * Instance of CakePHP core HttpSocket class
 *
 * @var HttpSocket
 */
	var $Http = null;

/**
 * Holds a configuration map
 *
 * @var string
 */
	var $map = array();
	
/**
 * The http client options
 * @var array
 */
	protected $options = array(
		'format'     			=> 'json',
		'param_separator'		=> '/',
		'key_value_separator'	=> null,
	);

/**
 * Loads HttpSocket class
 *
 * @param array $config
 * @param HttpSocket $Http
 */
	public function __construct($config, $Http = null) {
		parent::__construct($config);
	
		// Store the HttpSocket reference
		if (!$Http) {
			App::import('Lib', 'HttpSocketOauth.HttpSocketOauth');
			$Http = new HttpSocketOauth();
		}
		$this->Http = $Http;
	
		// Store the API configuration map
		$name = get_class($this);
		if (Configure::load($name . '.' . Inflector::underscore($name))) {
			$this->map = Configure::read('Apis.' . $name);
		}
	}
	
/**
 * Sends HttpSocket requests. Builds your uri and formats the response too.
 *
 * @param string $params
 * @param array $options
 *		method: get, post, delete, put
 *		data: either in string form: "option1=foo&option2=bar" or as a keyed array: array('option1' => 'foo', 'option2' => 'bar')
 * @return array $response
 * @author Dean Sofer
 */
	function request(&$model, $url = null, $options = array()) {
		if (is_object($model)) {
			if (!isset($model->request))
				$model->request = array();
			$request = $model->request;
		} elseif (is_array($model)) {
			$request = $model;
		} elseif (is_string($model)) {
			$request = array('uri' => $model);
		}
		
		$request = $this->addOauth($model, $request);

		// create full url
		$request['uri']['scheme'] = $this->options['scheme'];
		$request['uri']['host'] = $this->map['hosts']['rest'];
			//'format'   => $this->options['format'],
			//'path'     => trim($url, $this->options['param_separator']),
			//'login'	=> $this->options['login'],
			debug($request);

		// Remove unwanted elements from request array
		$request = array_intersect_key($request, $this->Http->request);

		// Issues request
		$response = $this->Http->request($request);
		
		if (is_object($model)) {
			$model->response = $response;
		}

		// Check response status code for success or failure
		if (substr($this->Http->response['status']['code'], 0, 1) != 2) {
			if (is_object($model) && method_exists($model, 'onError')) {
				$model->onError();
			}
			return false;
		}
		
		return $response;
	}
	
	/**
	 * Supplements a request array with oauth credentials
	 *
	 * @param object $model 
	 * @param array $request 
	 * @return array $request
	 */
	function addOauth(&$model, $request) {
		$request['auth']['method'] = 'OAuth';
		$request['auth']['oauth_consumer_key'] = $this->config['login'];
		$request['auth']['oauth_consumer_secret'] = $this->config['password'];
		if (isset($this->config['oauth_token'])) {
			$request['auth']['oauth_token'] = $this->config['oauth_token'];
		}
		if (isset($this->config['oauth_token_secret'])) {
			$request['auth']['oauth_token_secret'] = $this->config['oauth_token_secret'];
		}
		return $request;
	}
	
	/**
	 * Decodes the response based on the content type
	 *
	 * @param string $response 
	 * @return void
	 * @author Dean Sofer
	 */
	function decode($response) {
		// Get content type header
		$contentType = $this->Http->response['header']['Content-Type'];

		// Extract content type from content type header
		if (preg_match('/^([a-z0-9\/\+]+);\s*charset=([a-z0-9\-]+)/i', $contentType, $matches)) {
			$contentType = $matches[1];
			$charset = $matches[2];
		}
		
		// Decode response according to content type
		switch ($contentType) {
			case 'application/xml':
			case 'application/atom+xml':
			case 'application/rss+xml':
				// If making multiple requests that return xml, I found that using the
				// same Xml object with Xml::load() to load new responses did not work,
				// consequently it is necessary to create a whole new instance of the
				// Xml class. This can use a lot of memory so we have to manually
				// garbage collect the Xml object when we've finished with it, i.e. got
				// it to transform the xml string response into a php array.
				App::import('Core', 'Xml');
				$Xml = new Xml($response);
				$response = $Xml->toArray(false); // Send false to get separate elements
				$Xml->__destruct();
				$Xml = null;
				unset($Xml);
				break;
			case 'application/json':
			case 'text/javascript':
				$response = json_decode($response, true);
				break;
		}
		return $response;
	}
	
	function listSources() {
		return array_keys($this->_schema);
	}
	
	
/**
 * Generates a conditions section of the url
 *
 * @param array $params permitted conditions
 * @param array $queryData passed conditions in key => value form
 * @return string
 * @author Dean Sofer
 */
	function buildQuery($params = array(), $data = array()) {
		$query = array();
		foreach ($params as $param) {
			if (!empty($data[$param]) && $this->options['key_value_separator']) {
				$query[] = $param . $this->options['key_value_separator'] . $data[$param];
			} elseif (!empty($data[$param])) {
				$query[] = $data[$param];
			}
		}
		return implode($this->options['param_separator'], $query);
	}
	
	


/**
 * Uses standard find conditions. Use find('all', $params). Since you cannot pull specific fields,
 * we will instead use 'fields' to specify what table to pull from.
 *
 * @param string $model The model being read.
 * @param string $queryData An array of query data used to find the data you want
 * @return mixed
 * @access public
 */
	function read(&$model, $queryData = array()) {
		if (!isset($model->request)) {
			$model->request = array();
		}
		$model->request = array_merge(array('method' => 'GET'), $model->request);
		
		if (!isset($queryData['conditions'])) {
			$queryData['conditions'] = array();
		}
		if (!empty($this->map['read'][$queryData['fields']])) {
			$map = $this->map['read'][$queryData['fields']];
			foreach ($map as $path => $conditions) {
				$optional = (isset($conditions['optional'])) ? $conditions['optional'] : array();
				unset($conditions['optional']);
				if (array_intersect(array_keys($queryData['conditions']), $conditions) == $conditions) {
					$model->request['uri']['path'] = $path;
					$model->request['uri']['query'] = $this->buildQuery(array_merge($conditions, $optional), $queryData['conditions']);
					return $this->request($model);
				}
			}
		}
		return false;
	}

/**
 * Sets method = POST in request if not already set
 *
 * @param AppModel $model
 * @param array $fields Unused
 * @param array $values Unused
 */
	public function create(&$model, $fields = null, $values = null) {
		if (!isset($model->request)) {
			$model->request = array();
		}
		$model->request = array_merge(array('method' => 'POST'), $model->request);
		return $this->request($model);
	}

/**
 * Sets method = PUT in request if not already set
 *
 * @param AppModel $model
 * @param array $fields Unused
 * @param array $values Unused
 */
	public function update(&$model, $fields = null, $values = null) {
		if (!isset($model->request)) {
			$model->request = array();
		}
		$model->request = array_merge(array('method' => 'PUT'), $model->request);
		return $this->request($model);
	}

/**
 * Sets method = DELETE in request if not already set
 *
 * @param AppModel $model
 * @param mixed $id Unused
 */
	public function delete(&$model, $id = null) {
		if (!isset($model->request)) {
			$model->request = array();
		}
		$model->request = array_merge(array('method' => 'DELETE'), $model->request);
		return $this->request($model);
	}
}