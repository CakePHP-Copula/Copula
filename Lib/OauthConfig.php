<?php

App::uses('ConnectionManager', 'Model');

class OauthConfig {

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
	 * @param string $dbConfig The name of the Api config
	 * @param string $path The type of path to return, e.g. 'access', 'request', or 'authorize'
	 * @return string The assembled URI
	 */
	public static function getAuthUri($dbConfig, $path, $extra = array()) {
		$config = ConnectionManager::getDataSource($dbConfig)->config;
		if (!empty($config[$path])) {
			if ($config['authMethod'] == 'OAuth') {
				return $config['scheme'] . '://' . $config['host'] . '/' . $config[$path];
			} elseif ($config['authMethod'] == 'OAuthV2') {
				$uri = $config['scheme'] . '://' . $config['host'] . '/' . $config[$path];
				$query = array('redirect_uri' => $config['callback'], 'client_id' => $config['login']);
				if (!empty($config['scope'])) {
					$query['scope'] = $config['scope'];
				}
				return $uri . Router::queryString($query, $extra);
			}
		}
	}

}

?>