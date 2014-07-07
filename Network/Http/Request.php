<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         CakePHP(tm) v 3.0.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
#namespace Cake\Network\Http;

#use Cake\Error;
#use Cake\Network\Http\Message;

App::uses('Message', 'Copula.Network/Http');
/**
 * Implements methods for HTTP requests.
 *
 * Used by Cake\Network/Http\Client to contain request information
 * for making requests.
 */
class Request extends Message {

/**
 * The HTTP method to use.
 *
 * @var string
 */
	protected $_method = self::METHOD_GET;

/**
 * Request body to send.
 *
 * @var mixed
 */
	protected $_body;

/**
 * The URL to request.
 *
 * @var string
 */
	protected $_url;

/**
 * Headers to be sent.
 *
 * @var array
 */
	protected $_headers = [
		'Connection' => 'close',
		'User-Agent' => 'CakePHP'
	];

/**
 * Get/Set the HTTP method.
 *
 * @param string|null $method The method for the request.
 * @return mixed Either this or the current method.
 * @throws CakeException On invalid methods.
 */
	public function method($method = null) {
		if ($method === null) {
			return $this->_method;
		}
		$name = get_called_class() . '::METHOD_' . strtoupper($method);
		if (!defined($name)) {
			throw new CakeException(__d('cake_dev', 'Invalid method type'));
		}
		$this->_method = $method;
		return $this;
	}

/**
 * Get/Set the url for the request.
 *
 * @param string|null $url The url for the request. Leave null for get
 * @return mixed Either $this or the url value.
 */
	public function url($url = null) {
		if ($url === null) {
			return $this->_url;
		}
		$this->_url = $url;
		return $this;
	}

/**
 * Get/Set headers into the request.
 *
 * You can get the value of a header, or set one/many headers.
 * Headers are set / fetched in a case insensitive way.
 *
 * ### Getting headers
 *
 * `$request->header('Content-Type');`
 *
 * ### Setting one header
 *
 * `$request->header('Content-Type', 'application/json');`
 *
 * ### Setting multiple headers
 *
 * `$request->header(['Connection' => 'close', 'User-Agent' => 'CakePHP']);`
 *
 * @param string|array $name The name to get, or array of multiple values to set.
 * @param string $value The value to set for the header.
 * @return mixed Either $this when setting or header value when getting.
 */
	public function header($name = null, $value = null) {
		if ($value === null && is_string($name)) {
			$name = $this->_normalizeHeader($name);
			return isset($this->_headers[$name]) ? $this->_headers[$name] : null;
		}
		if ($value !== null && !is_array($name)) {
			$name = [$name => $value];
		}
		foreach ($name as $key => $val) {
			$key = $this->_normalizeHeader($key);
			$this->_headers[$key] = $val;
		}
		return $this;
	}

/**
 * Get/Set cookie values.
 *
 * ### Getting a cookie
 *
 * `$request->cookie('session');`
 *
 * ### Setting one cookie
 *
 * `$request->cookie('session', '123456');`
 *
 * ### Setting multiple headers
 *
 * `$request->cookie(['test' => 'value', 'split' => 'banana']);`
 *
 * @param string $name The name of the cookie to get/set
 * @param string|null $value Either the value or null when getting values.
 * @return mixed Either $this or the cookie value.
 */
	public function cookie($name, $value = null) {
		if ($value === null && is_string($name)) {
			return isset($this->_cookies[$name]) ? $this->_cookies[$name] : null;
		}
		if (is_string($name) && is_string($value)) {
			$name = [$name => $value];
		}
		foreach ($name as $key => $val) {
			$this->_cookies[$key] = $val;
		}
		return $this;
	}


	/**
	 * Request::isValid()
	 * This validates either querystring parameters or request body parameters using AppModel and the validation array from $this->map
	 *
	 * @param array An array of validation rules
	 * @return bool Success Whether the request is valid
	 */
	public function isValid($validationRules) {
		$url = $this->url();
		//If the query is GET, assume all params are querystring
		//Otherwise assume they are all body params
		if ($this->method() === 'GET') {
			$fieldsToValidate = $url['query'];
		} else {
			$fieldsToValidate = $this->body();
		}
		//we don't really want this class to extend Mode/AppModel
		//it's not trying to be part of the ORM to that degree, I think
		//for example, it would be crazy to have relations here
		//using Validator directly might be nice though
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
}
