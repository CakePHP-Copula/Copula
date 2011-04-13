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
 * Array containing the names of components this component uses. Component names
 * should not contain the "Component" portion of the classname.
 *
 * @var array
 * @access public
 */
	var $config = array();
	
	// TODO: Relocate to a dedicated schema file
	var $_schema = array();
	
	var $socket;
	
	var $map = array();
	
/**
 * The http client options
 * @var array
 */
	protected $options = array(
		'protocol'   			=> 'http',
		'format'     			=> 'json',
		'user_agent' 			=> 'cakephp apis datasource',
		'http_port'  			=> 80,
		'timeout'    			=> 10,
		'login'      			=> null,
		'token'      			=> null,
		'param_separator'		=> '/',
		'key_value_separator'	=> null,
	);
	
	protected $url = ':protocol://github.com/api/v2/:format/:path';
	
	function __construct($config) {
		App::import('Core', 'HttpSocket');
		$this->socket = new HttpSocket();
		if (!empty($config['login']))
			$this->options['login'] = $config['login'];
		$name = get_class($this);
		if (Configure::load($name . '.' . $name))
			$this->map = Configure::read('Apis.' . $name);
			
		parent::__construct($config);
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
	function _request($params, $options = array()) {
		$options = array_merge(array(
			'method' => 'get',
			'data' => array(),
		), $options);

		// create full url
		$url = strtr($this->url, array(
			':protocol' => $this->options['protocol'],
			':format'   => $this->options['format'],
			':path'     => trim($params, $this->options['param_separator']),
			':login'	=> $this->options['login'],
		));
		$response = $this->socket->{$options['method']}($url, $options['data']);
		if ($this->options['format'] == 'json') {
			$response = json_decode(preg_replace('/.+?({.+}).+/', '$1', $response), true);
		}
		return $response;
	}

	// TODO: Add support for true schemas
	function describe($model) {
		return $this->_schema['repositories'];
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
	function _buildParams($params = array(), $data = array()) {
		$url = array();
		foreach ($params as $param) {
			if (!empty($data[$param]) && $this->options['key_value_separator']) {
				$url[] = $param . $this->options['key_value_separator'] . $data[$param];
			} elseif (!empty($data[$param])) {
				$url[] = $data[$param];
			}
		}
		return implode($this->options['param_separator'], $url);
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
	function read($model, $queryData = array()) {
		if (!empty($this->map['read'][$queryData['fields']])) {
			$map = $this->map['read'][$queryData['fields']];
			foreach ($map as $path => $conditions) {
				$optional = (isset($conditions['optional'])) ? $conditions['optional'] : array();
				unset($conditions['optional']);
				if (array_intersect(array_keys($queryData['conditions']), $conditions) == $conditions) {
					$url = $path . $this->options['param_separator'] . $this->_buildParams(array_merge($conditions, $optional), $queryData['conditions']);
					return $this->_request($url);
				}
			}
		}
		return false;
	}
}