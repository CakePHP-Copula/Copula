<?php

App::uses('ConnectionManager', 'Model');

class OauthCredentials {

	public static $initialized = false;
	public static $Apis = array();

	/**
	 * 
	 * @param string $apiName
	 * @return array array containing 'key' and 'secret' for oauth actions
	 */
	public static function getCredentials($apiName) {
		if (!self::$initialized) {
			OauthCredentials::_init();
		}
		if (!empty(self::$Apis[$apiName])) {
			$credentials = array('key' => self::$Apis[$apiName]['login'], 'secret' => self::$Apis[$apiName]['password']);
			return $credentials;
		}
	}
/**
 * 
 * @param type $apiName
 * @return array
 */
	public static function getAccessToken($apiName) {
		if(!self::$initialized){
			self::_init();
		}
		if(!empty(self::$Apis[$apiName]) && !empty(self::$Apis[$apiName]['access_token'])){
                        if (self::$Apis[$apiName]['authMethod'] == 'OAuth') {
                                $tokens = array(
                                        'access_token' => self::$Apis[$apiName]['access_token'],
                                        'token_secret' => (isset(self::$Apis[$apiName]['token_secret']))? self::$Apis[$apiName]['token_secret'] : null,
                                );
                        } else {
                                $tokens = array(
                                        'access_token' => self::$Apis[$apiName]['access_token'],
                                        'refresh_token' => '',
                                );
                        }
			return array_filter($tokens);
		}
		return array();
	}

	protected static function _init() {
		$objects = ConnectionManager::enumConnectionObjects();
		foreach ($objects as $name => $connection) {
			if (!empty($connection['authMethod']) && ($connection['authMethod'] == 'OAuth' || $connection['authMethod'] == 'OAuthV2')) {
				self::$Apis[$name] = $connection;
			}
		}
		self::$initialized = true;
	}

	/**
	 * 
	 * @param string $apiName
	 * @param string $token
	 * @param string $tokenSecret
	 * @throws MissingDataSourceConfigException
	 * @return boolean
	 */
	public static function setAccessToken($apiName, $token, $tokenSecret = null) {
		if(!self::$initialized){
			self::_init();
		}
		if (isset(self::$Apis[$apiName])) {
			self::$Apis[$apiName]['access_token'] = $token;
			if ($tokenSecret) {
				self::$Apis[$apiName]['token_secret'] = $tokenSecret;
			}
			return true;
		} else {
			throw new MissingDatasourceConfigException;
		}
	}
	public static function reset(){
		self::$Apis = array();
		self::$initialized = false;
	}
}

?>