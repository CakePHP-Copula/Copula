<?php

App::uses('ConnectionManager', 'Model');

class OauthConfig {

	public static function getConfiguredApis() {
		$objects = ConnectionManager::enumConnectionObjects();
		$apis = array();
		foreach ($objects as $name) {
			if ($this->isOauthApi($name)) {
				$apis[] = $name;
			}
		}
		return array_keys($apis);
	}

	/**
	 * 
	 * @param string $dbConfig
	 * @return array array containing 'key' and 'secret' for oauth actions
	 */
	public static function getCredentials($dbConfig) {
		$config = ConnectionManager::getDataSource($dbConfig)->config;
		$credentials = array('key' => $config['login'], 'secret' => $config['password']);
		return $credentials;
	}

	/**
	 * 
	 * @param string $dbConfig
	 * @return array
	 */
	public static function getAccessToken($dbConfig) {
		$config = ConnectionManager::getDataSource($dbConfig)->config;
		if (!empty($config['access_token'])) {
			$token = $config['access_token'];
			$tokenSecret = (isset($config['token_secret'])) ? $config['token_secret'] : null;
			return array_filter(array($token, $tokenSecret));
		}
		return array();
	}

	/**
	 * 
	 * @param string $dbConfig
	 * @return string|boolean
	 */
	public static function isOauthApi($dbConfig) {
		$config = ConnectionManager::getDataSource($dbConfig)->config;
		if (in_array($config['authMethod'], array('OAuth', 'OAuthV2'))) {
			return $config['authMethod'];
		} else {
			return false;
		}
	}

	/**
	 * 
	 * @param string $dbConfig
	 * @param string $token
	 * @param string $tokenSecret
	 * @return boolean
	 */
	public static function setAccessToken($dbConfig, $token, $tokenSecret = null) {
		ConnectionManager::getDataSource($dbConfig)->config['access_token'] = $token;
		if ($tokenSecret) {
			ConnectionManager::getDataSource($dbConfig)->config['token_secret'] = $tokenSecret;
		}
		return true;
	}

	/**
	 * 
	 * @param string $dbConfig The name of the Api config
	 * @param string $path The type of path to return, e.g. 'access', 'request', or 'authorize'
	 * @return string The assembled URI
	 */
	public static function getRequestUri($dbConfig, $path) {
		$config = ConnectionManager::getDataSource($dbConfig)->config;
		if (!empty($config[$path])) {
			return $config['scheme'] . $config['host'] . $config[$path];
		}
	}

}

?>