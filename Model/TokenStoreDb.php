<?php

/**
 *
 * @subpackage model
 * @package Copula
 */
App::uses('TokenStoreInterface', 'Copula.Lib');
App::uses('CopulaAppModel', 'Copula.Model');
App::uses('TokenStoreBehavior', 'Copula.Model/Behavior');

class TokenStoreDb extends CopulaAppModel implements TokenStoreInterface {

	public $name = "TokenStoreDb";
	public $useDbConfig = "default";
	public $actsAs = array('Copula.TokenStore');
	public $useTable = "tokens";
	public $validate = array();
        
        public function __construct($id = false, $table = null, $ds = null) {
                $this->validate = array(
                        'id' => array(),
                        'user_id' => array(
                                'numeric' => array(
                                        'rule' => 'notEmpty',
                                        'message' => __('user_id must be setted')
                                ),
                                'unique' => array(
                                        'rule' => 'isUnique',
                                        'message' => __('user_id must be unique')
                                )
                        ),
                        'api' => array(
                                'alphaNumeric' => array(
                                        'rule' => 'alphaNumeric',
                                        'message' => __('API names must be alphanumeric. In point of fact they should probably be camelcased singular.')
                                )
                        )                
                );
                parent::__construct($id, $table, $ds);
        }

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
		$result = (empty($result)) ? $result : $result[$this->alias];
		return $result;
	}

	function checkToken($user_id, $apiName) {
		$conditions = array('user_id' => $user_id, 'api' => $apiName);
		return $this->hasAny($conditions);
	}

	/**
	 * Convenience method for saving.
	 *
	 * Data is munged with behavior callbacks afterwards.
	 * @param string $user_id
	 * @param array $access_token
	 * @param string $apiName
	 */
	function saveToken(array $access_token, $apiName, $user_id, $version) {
		$this->data = array(
			'user_id' => $user_id,
			'api' => $apiName
		);
		if ($version == 'OAuth' || $version == '1.0') {
			$this->data['access_token'] = $access_token['oauth_token'];
			$this->data['token_secret'] = $access_token['oauth_token_secret'];
		} elseif ($version == 'OAuthV2' || $version == '2.0') {
			$this->data['access_token'] = $access_token['access_token'];
			$this->data['refresh_token'] = $access_token['refresh_token'];
			$this->data['expires_in'] = $access_token['expires_in'];
		}
		return $this->save($this->data);
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
					'user_id' => $this->data[$this->alias]['user_id'],
					'api' => $this->data[$this->alias]['api']
				),
				'callbacks' => 'before'
					));
			if (!empty($existing[0][$this->alias])) {
				$this->id = $existing[0][$this->alias]['id'];
			}
		}
		return true;
	}

}

?>