<?php

App::uses('BaseAuthorize', 'Controller/Component/Auth');
App::uses('Token', 'Apis.Model');
App::uses('OauthConfig', 'Apis.Lib');
/**
 * @property Token $Token
 */
class OauthAuthorize extends BaseAuthorize {

	public $Token;

	public function __construct(\ComponentCollection $collection, $settings = array()) {
		$this->Token = ClassRegistry::init('Apis.Token');
		$defaults = array('Apis' => array());
		$settings = array_merge($defaults, $settings);
		parent::__construct($collection, $settings);
	}

	public function authorize($user, \CakeRequest $request) {
		$dbs = ConnectionManager::sourceList();
		$apiNames = array_intersect(array_keys($this->settings['Apis']), array_values($dbs));
		$count = 0;
		foreach ($apiNames as $index => $name) {
			switch ($this->settings['Apis'][$name]['store']) {
				case 'Session':
					$allowed = $this->_checkTokenSession($name, $user['id']);
					break;
				case 'Cookie':
					$allowed = $this->_checkTokenCookie($name, $user['id']);
					break;
				default:
					$allowed = $this->_checkTokenDb($name, $user['id']);
					break;
			}
			if ($allowed) {
				$count++;
			}
		}
		if ($count == count($apiNames)) {
			return true;
		}
		return false;
	}
        
        private function __setToken($apiName, $token) {
		if (!empty($token['access_token'])) {
			$tokenSecret = (empty($token['token_secret'])) ? null : $token['token_secret'];
			OauthConfig::setAccessToken(strtolower($apiName), $token['access_token'], $tokenSecret);
			return true;
		} else {
			//$this->controller()->Auth->flash('Not authorized to access ' . $apiName . ' Api functions');
			return false;
		}  
        }
        
	protected function _checkTokenDb($apiName, $userId) {
		$token = $this->Token->getTokenDb($userId, $apiName);
                return $this->__setToken($apiName, $token);
	}

	protected function _checkTokenSession($apiName, $userId) {
		App::uses('CakeSession', 'Model/Datasource');
		$token = CakeSession::read('Oauth.' . $apiName);
		return $this->__setToken($apiName, $token);
	}

	protected function _checkTokenCookie($apiName, $userId) {
		//not implemented
		//$this->controller()->Auth->flash('Not authorized to access ' . $apiName . ' Api functions');
		return false;
	}

}

?>