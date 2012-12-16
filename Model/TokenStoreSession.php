<?php

App::uses('TokenStoreInterface', 'Apis.Lib');
App::uses('CakeSession', 'Model/Datasource');
App::uses('ApisAppModel', 'Apis.Model');
App::uses('TokenStoreBehavior', 'Apis.Model/Behavior');

class TokenStoreSession extends ApisAppModel implements TokenStoreInterface {

	public $useTable = false;
	public $table = false;
	public $actsAs = array('Apis.TokenStore');
	var $validate = array(
		'id' => array(),
		'user_id' => array(
			'numeric' => array(
				'rule' => 'numeric',
				'message' => 'user_id must be numeric'
			)
		),
		'api' => array(
			'alphaNumeric' => array(
				'rule' => 'alphaNumeric',
				'message' => 'API names must be alphanumeric. In point of fact they should probably be camelcased singular.'
			)
		)
	);

	public function saveToken(array $access_token, $apiName, $userId, $version) {
		$data = array(
			'user_id' => $userId,
			'api' => $apiName
		);
		if ($version == 'OAuth' || $version == '1.0') {
			$data['access_token'] = $access_token['oauth_token'];
			$data['token_secret'] = $access_token['oauth_token_secret'];
		} elseif ($version == 'OAuthV2' || $version == '2.0') {
			$data['access_token'] = $access_token['access_token'];
			$data['refresh_token'] = $access_token['refresh_token'];
			$data['expires'] = $access_token['expires'];
		}
		$this->set($data);
		if ($this->validates()) {
			CakeSession::write("Apis.$apiName.$userId", $data);
			return $this->data;
		} else {
			return false;
		}
	}

	public function getToken($userId, $apiName) {
		$token = CakeSession::read("Apis.$apiName.$userId");
		if (!empty($token['expires']) && $this->isExpired($token)) {
			$refresh = $this->getRefreshAccess($token);
			if (!empty($refresh)) {
				$token = array_merge($token, $refresh);
				$this->saveToken($token, $apiName, $userId, '2.0');
			} else {
				throw new CakeException(__('Expired token for Api %s could not be refreshed.', $apiName));
			}
		}
		return $token;
	}

	public function checkToken($userId, $apiName) {
		return CakeSession::check("Apis.$apiName.$userId");
	}

}

?>