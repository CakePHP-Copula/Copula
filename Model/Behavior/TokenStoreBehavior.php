<?php

/**
 * Description of AccessToken
 *
 * @package cake
 * @subpackage apis
 */
App::uses('ModelBehavior', 'Model');

class TokenStoreBehavior extends ModelBehavior {

	public $config = array();

	/**
	 *
	 * @param \Model $Model
	 * @param type $config
	 * @throws CakeException
	 * @return void
	 */
	public function setup(\Model $Model, $config = array()) {
		$this->TokenSource = ClassRegistry::init('Apis.TokenSource');
	}

	/**
	 * @param \Model $model
	 * @param array $access_token
	 * @return array $access_token refreshed access token
	 */
	public function getRefreshAccess(\Model $model, $access_token) {
		$options = array(
			'api' => $access_token['api'],
			'requestToken' =>
			array('grantType' => 'refresh', 'code' => $access_token['refresh_token'])
		);
		return $this->TokenSource->find('access', $options);
	}

	/**
	 * As written this is more of a "best-guess". The only way we can really be sure that a token is expired is to try to use it.
	 * @param \Model $model
	 * @param array $token array containing an OAuth2 token
	 * @return boolean
	 */
	public function isExpired(\Model $model, $token) {
		$expires = $token['expires'];
		$now = strtotime('now');
		$modified = strtotime($token['modified']);
		$interval = $now - $modified;
		return ($interval > $expires) ? true : false;
	}

	/**
	 *
	 * @param \Model $model
	 * @param array $results
	 * @param boolean $primary
	 * @return array
	 * @throws CakeException
	 */
	public function afterFind(\Model $model, $results, $primary) {
		if ($primary &&
				!empty($results[0][$model->alias]['access_token']) &&
				!empty($results[0][$model->alias]['expires'])) {
			$token = $results[0][$model->alias];
			if ($this->isExpired($model, $token)) {
				$refresh = $this->getRefreshAccess($model, $token);
				if (!empty($refresh)) {
					$model->saveToken($refresh, $token['user_id'], $token['api']);
					$results = array_merge($results[0][$model->alias], $refresh);
				} else {
					throw new CakeException(__('Expired token for Api %s could not be refreshed.', $token['api']));
				}
			}
		}
		return $results;
	}

}

?>