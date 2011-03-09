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
	
	/**
     * The http client options
     * @var array
     */
    protected $options = array(
        'protocol'   		=> 'http',
        'format'     		=> 'json',
        'user_agent' 		=> 'cakephp apis datasource',
        'http_port'  		=> 80,
        'timeout'    		=> 10,
        'login'      		=> null,
        'token'      		=> null,
        'param_separator'	=> '/',
    );
    
    protected $url = ':protocol://github.com/api/v2/:format/:path';
	
	function __construct($config) {
		App::import('Core', 'HttpSocket');
		$this->socket = new HttpSocket();
		if (!empty($config['login']))
			$this->options['login'] = $config['login'];
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
			$response = json_decode($response, true);
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
	
	function _buildParams($params = array(), $queryData = array()) {
		$uri = array();
		foreach ($params as $param) {
			if (!empty($queryData['conditions'][$param]))
				$uri[] = $queryData['conditions'][$param];
		}
		return implode($this->options['param_separator'], $uri);
	}
}