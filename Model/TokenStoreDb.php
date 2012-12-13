<?php

/**
 *
 * @subpackage model
 * @package Apis
 */
class TokenStoreDb extends ApisAppModel {

	public $name = "TokenStore";
	public $useDbConfig = "default";
	//public $actsAs = array('TokenStore');
	public $useTable = "tokens";
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

	/**
	 * @param string $user_id  the associated user id
	 * @param string $apiName the name of an API to search for
	 * @return array token data
	 */
	function getToken($user_id, $apiName) {
		$result = $this->find('first', array(
			'conditions' => array(
				'user_id' => $user_id,
				'api' => $apiName
				)));
		$result = (empty($result)) ? $result : $result['Token'];
		return $result;
	}

	/**
	 * Convenience method for saving.
	 *
	 * Data is munged with behavior callbacks afterwards.
	 * @param string $user_id
	 * @param array $access_token
	 * @param string $apiName
	 */
	function saveToken(array $access_token, $apiName, $user_id) {
		if (!empty($access_token['oauth_token'])) {
			$data = array('Token' => array(
				'access_token' => $access_token['oauth_token'],
				'token_secret' => $access_token['oauth_token_secret'],
				'user_id' => $user_id,
				'api' => $apiName
			));
		} else {
			$data = array('Token' => array(
					'user_id' => $user_id,
				'access_token' => $access_token['access_token'],
				'refresh_token' => $access_token['refresh_token'],
					'api' => $apiName
					));
		}
		return $this->save($data);
	}

	/**
	 * Checks for existing tokens before saving.
	 * @param array $options
	 * @return boolean
	 */
	function beforeSave($options = array()) {
		if (!$this->isUnique(array('api', 'user_id'), false)) {
			$existing = $this->find('all', array(
				'conditions' => array(
					'user_id' => $this->data['Token']['user_id'],
					'api' => $this->data['Token']['api']
				),
				'callbacks' => 'before'
					));
			if (!empty($existing[0]['Token'])) {
				$this->id = $existing[0]['Token']['id'];
			}
		}
		return true;
	}

}

?>