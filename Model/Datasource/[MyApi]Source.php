<?php
/**
 * Apis DataSource
 *
 * [Short Description]
 *
 * @package default
 * @author Dean Sofer
 **/
App::uses('ApisSource', 'Apis.Model/Datasource');
class [MyApi]Source extends ApisSource {

/**
 * The description of this data source
 *
 * @var string
 */
	public $description = '[MyApi] Api DataSource';
/**
 * Holds the datasource configuration
 *
 * @var array
 */
	public $config = array();
/**
 * Holds a configuration map
 *
 * @var array
 */
	public $map = array();
/**
 * API options
 * @var array
 */
	public $options = array(
		'format'    => 'json',
		'ps'		=> '&', // param separator
		'kvs'		=> '=', // key-value separator
	);
/**
 * Loads HttpSocket class
 *
 * @param array $config
 * @param HttpSocket $Http
 */
	public function __construct($config, $Http = null) {
		parent::__construct($config);
	}
/**
 * Just-In-Time callback for any last-minute request modifications
 *
 * @param object $model
 * @param array $request
 * @return array $request
 */
	public function beforeRequest(&$model, $request) {
		return $request;
	}
/**
 * Stores the queryData so that the tokens can be substituted just before requesting
 *
 * @param string $model
 * @param string $queryData
 * @return mixed $data
 */
	public function read(&$model, $queryData = array()) {
		// $this->tokens = $queryData['conditions']; // Swap out tokens for passed conditions
		return parent::read($model, $queryData);
	}
}