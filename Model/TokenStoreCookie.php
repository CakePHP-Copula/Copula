<?php

App::uses('TokenStoreInterface', 'Copula.Lib');
App::uses('CopulaAppModel', 'Copula.Model');
App::uses('TokenStoreBehavior', 'Copula.Model/Behavior');

class TokenStoreCookie extends CopulaAppModel implements TokenStoreInterface {

	public $useTable = false;
	public $actsAs = array('Copula.TokenStore');
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