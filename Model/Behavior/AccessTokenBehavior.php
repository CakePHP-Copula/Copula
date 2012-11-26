<?php

App::uses('HttpSocket', 'Network/Http');

/**
 * Description of AccessToken
 *
 * @package cake
 * @subpackage apis
 */
class AccessTokenBehavior extends ModelBehavior {

	public $config = array();

/**
 * 
 * @param \Model $Model
 * @param type $config
 * @throws CakeException
 * @return void
 */
	public function setup(\Model $Model, $config = array()) {
		if (!isset($this->config[$Model->alias])) {
			$this->config[$Model->alias] = array(
				'expires' => '3600',
				'Api' => $Model->useDbConfig,
				'useSesson' => false
			);
		}
		$this->config[$Model->alias] = array_merge(
				$this->config[$Model->alias], (array)$config);
		Configure::load($this->config[$Model->alias]['Api'] . '.' . $this->config[$Model->alias]['Api'] . "Source");
		$oauth = Configure::read('Apis.' . $this->config[$Model->alias]['Api']);
		if (!empty($oauth)) {
			$path = $oauth['oauth']['scheme'] . '//' . $oauth['hosts']['oauth'] . '/' . $oauth['oauth']['access'];
			$Model->Socket = new HttpSocket($path);
		} else {
			throw new CakeException('API not configured.');
		}
	}

/**
 * Used to get Oauth Tokens for the configured API.
 * @param \Model $model
 * @param string $oAuthCode
 * @param string $grantType
 * @return array array containing access token values
 * @throws CakeException
 */
	public function getRemoteToken(\Model $model, $oAuthCode, $grantType) {
		Configure::load($this->config[$model->alias]['Api'] . '.' . $this->config[$model->alias]['Api'] . "Source");
		$credentials = $this->getCredentials($model, $this->config[$model->alias]['Api']);
		$config = Configure::read('Apis.' . $this->config[$model->alias]['Api']);
		$request = array(
			'method' => 'POST');
		$body = array(
			'client_id' => $credentials['key'],
			'client_secret' => $credentials['secret'],
			'grant_type' => $grantType
		);
		if ($grantType == "refresh_token") {
			$body['refresh_token'] = $oAuthCode;
		} elseif ($grantType == "authorization_code") {
			$body['code'] = $oAuthCode;
		}
		$body = substr(Router::queryString($body), 1);
		if ($grantType == "authorization_code") {
			//append redirect URI to body.
			//it should not be encoded
			$body .= "&redirect_uri=" . $config['callback'];
		}
		$request['body'] = $body;
		$response = $model->Socket->request($request);
		if ($response->isOk()) {
			return json_decode($response->body, true);
		} else {
			throw new CakeException($response->body, $response->code);
		}
	}

/**
 * Returns oauth credentials for the API
 * @param \Model $model
 * @return array containing oauth credentials for the datasource
 */
	public function getCredentials(\Model $model) {
		$ds = ConnectionManager::getDataSource(strtolower($this->config[$model->alias]['Api']));
		$credentials = array('key' => $ds->config['login'], 'secret' => $ds->config['password']);
		return $credentials;
	}

	/**
	 * @param \Model $model
	 * @param array $access_token
	 * @param string $user_id
	 * @return array $access_token refreshed access token
	 */
	public function getRefreshAccess(\Model $model, $access_token) {
		$refresh = $this->getRemoteToken($model, $access_token['refresh_token'], 'refresh_token');
		if ($refresh) {
			$access_token = array_merge($access_token, $refresh);
			// I do this twice. I'm not sure if it's a good idea either time. Alternative would be to create an instance in setup.
			$Token = ClassRegistry::init('Apis.Token');
			$Token->id = $access_token['id'];
			$token = $Token->saveTokenDb($access_token, $this->config[$model->alias]['Api']);
			return $token['Token'];
		}
	}

	/**
	 * As written this is more of a "best-guess". The only way we can really be sure that a token is expired is to try to use it.
	 * @param \Model $model
	 * @param array $token array containing an OAuth2 token
	 * @return boolean
	 */
	public function isExpired(\Model $model, $token) {
		$expires = $this->config[$model->alias]['expires'];
		$now = strtotime('now');
		$modified = strtotime($token['modified']);
		$interval = $now - $modified;
		return ($interval > $expires) ? true : false;
	}

	/**
	 * Fetches Token an verifies token validity.
	 * @param \Model $model
	 * @param array $results
	 * @param boolean $primary
	 * @return array array containing an access token
	 */
	public function getToken(\Model $model, $user_id) {
		$Token = ClassRegistry::init('Apis.Token');
		$token = $Token->getTokenDb($user_id, $this->config[$model->alias]['Api']);
		if (!empty($token['access_token']) && $this->isExpired($model, $token)) {
			return $this->getRefreshAccess($model, $token);
		}
		return $token;
	}

	/**
	 * Utility method to smoothly switch dbconfigs without woes
	 * @param \Model $model
	 * @param string $source
	 * @param string $useTable
	 * @return void
	 * @author Ceeram
	 */
	public function setDbConfig(\Model $model, $source = null, $useTable = null) {
		$datasource = $this->getDataSource();
		if (method_exists($datasource, 'flushMethodCache')) {
			$datasource->flushMethodCache();
		}
		if ($source) {
			$this->config[$model->alias]['default'] = array('useTable' => $this->useTable, 'useDbConfig' => $this->useDbConfig);
			$this->setDataSource($source);
			if ($useTable !== null) {
				$this->setSource($useTable);
			}
		} else {
			if (!empty($this->config[$model->alias]['default'])) {
				$this->setDataSource($this->config[$model->alias]['default']['useDbConfig']);
				$this->setSource($this->config[$model->alias]['default']['useTable']);
				$this->config[$model->alias]['default'] = array();
			}
		}
	}

}

?>