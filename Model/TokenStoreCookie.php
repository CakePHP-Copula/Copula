<?php

App::uses('TokenStoreInterface', 'Apis.Lib');
App::uses('ApisAppModel', 'Apis.Model');
App::uses('TokenStoreBehavior', 'Apis.Model/Behavior');

class TokenStoreCookie extends ApisAppModel implements TokenStoreInterface {

	public $useTable = false;
	public $actsAs = array('Apis.TokenStore');
	var $validate = array(
		'id' => array(),
		'user_id' => array(
			'numeric' => array(
				'rule' => 'numeric',
				'message' => 'user_id must be numeric'
			),
			'unique' => array(
				'rule' => 'isUnique',
				'message' => 'user_id must be unique'
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
		return false;
	}

	public function getToken($userId, $apiName) {
		return false;
	}

	public function checkToken($userId, $apiName) {
		return false;
	}

}

?>