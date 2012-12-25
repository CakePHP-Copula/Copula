<?php

App::uses('TokenStoreInterface', 'Copula.Lib');
App::uses('CakeSession', 'Model/Datasource');
App::uses('CopulaAppModel', 'Copula.Model');
App::uses('TokenStoreBehavior', 'Copula.Model/Behavior');

class TokenStoreSession extends CopulaAppModel implements TokenStoreInterface {

	public $useTable = false;
	public $table = false;
	public $actsAs = array('Copula.TokenStore');
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
			$data['expires_in'] = $access_token['expires_in'];
		}
		$this->set($data);
		if ($this->validates()) {
			CakeSession::write("Copula.$apiName.$userId", $data);
			return $this->data;
		} else {
			return false;
		}
	}

	public function getToken($userId, $apiName) {
		$token = CakeSession::read("Copula.$apiName.$userId");
		if (!empty($token['expires_in']) && $this->isExpired($token)) {
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
		return CakeSession::check("Copula.$apiName.$userId");
	}

}

?>