<?php

App::uses('TokenSource', 'Copula.Model');
App::uses('TokenStoreDb', 'Copula.Model');
App::uses('OauthConfig', 'Copula.Lib');
App::uses('CakeSession', 'Model/Datasource');

/**
 * @property SessionComponent $Session
 * @property TokenSource $TokenSource
 * @property Controller $controller
 */
class OauthComponent extends Component {

	public $components = array('Session');
	public $controller;

	function __construct(\ComponentCollection $collection, $settings = array()) {
		$this->TokenSource = ClassRegistry::init('Copula.TokenSource');
		parent::__construct($collection, $settings);
	}

	function beforeRedirect(\Controller $controller, $url, $status = null, $exit = true) {
		$failed = $this->_checkAuthFailure($controller);
		if (!empty($failed)) {
			return false;
		}
	}

	function startup(\Controller $controller) {
		$this->controller = $controller;
		$failed = $this->_checkAuthFailure($controller);
		if (!empty($failed)) {
			CakeSession::write('Oauth.redirect', $controller->request->here);
			$apiName = array_pop($failed);
			unset($this->controller->Apis[$apiName]['authorized']);
			return $this->connect($apiName);
		}
	}

	protected function _checkAuthFailure(Controller $controller) {
		if (!empty($controller->Apis)) {
			$failed = array();
			foreach ($controller->Apis as $apiName => $config) {
				if (isset($config['authorized']) && !$config['authorized']) {
					$failed[] = $apiName;
				}
			}
			return $failed;
		}
	}

	/**
	 *
	 * @param string $apiName
	 * @param string $requestToken
	 * @param array $extra extra parameters for the querystring
	 */
	public function authorize($apiName, $requestToken, $extra = array()) {
		$query = Router::queryString(array('oauth_token' => $requestToken), $extra);
		$uri = OauthConfig::getAuthUri($apiName . 'Token', 'authorize');
		$this->controller->redirect($uri . $query);
	}

	/**
	 *
	 * @param string $apiName
	 * @param array $requestOptions
	 */
	public function authorizeV2($apiName, $requestOptions = array()) {
		$options = array_merge(array(
			'response_type' => 'code',
			'access_type' => 'offline',
				), $requestOptions);
		$uri = OauthConfig::getAuthUri($apiName . 'Token', 'authorize', $options);
		$this->controller->redirect($uri);
	}

	/**
	 *
	 * @param string $apiName
	 * @param array $authVars
	 * @param array $requestOptions
	 * @return array
	 */
	public function getAccessToken($apiName, array $authVars, $requestOptions = array()) {
		$options = array(
			'options' => $requestOptions,
			'requestToken' => $authVars,
			'api' => $apiName
		);
		return $this->TokenSource->find('access', $options);
	}

	/**
	 *
	 * @param string $apiName
	 * @param string $token
	 * @param array $requestOptions
	 * @return array
	 */
	public function getAccessTokenV2($apiName, $token, $requestOptions = array()) {

		$options = array(
			'api' => $apiName,
			'requestToken' =>
			array('grantType' => 'access', 'code' => $token),
			'options' => $requestOptions
		);
		return $this->TokenSource->find('access', $options);
	}

	/**
	 *
	 * @param string $apiName
	 * @param array $requestOptions
	 * @return array
	 */
	function getOauthRequestToken($apiName, $requestOptions = array()) {
		$options = array('api' => $apiName, 'options' => $requestOptions);
		return $this->TokenSource->find('request', $options);
	}

	/**
	 *
	 * @param string $apiName
	 */
	function connect($apiName) {
		$method = OauthConfig::isOauthApi($apiName . 'Token');
		if ($method) {
			if ($method == 'OAuthV2') {
				$this->authorizeV2($apiName);
			} else {
				$token = $this->getOauthRequestToken($apiName);
				if ($token) {
					$this->Session->write("Oauth.$apiName.request_token", $token);
					$this->authorize($apiName, $token['oauth_token']);
				} else {
					throw new CakeException(__('No Request Token is present for the %s API.', $apiName));
				}
			}
		} else {
			return false;
		}
	}

	/**
	 *
	 * @param string $apiName
	 * @throws CakeException
	 */
	function callback($apiName) {
		$method = OauthConfig::isOauthApi($apiName . 'Token');
		if ($method == 'OAuthV2') {
			$code = $this->controller->request->query('code');
			if (empty($code)) {
				throw new CakeException(__('Authorization token for API %s not received.', $apiName));
			}
			$accessToken = $this->getAccessTokenV2($apiName, $code);
			if (!empty($accessToken)) {
				return $this->_afterRequest($accessToken, $apiName, $method);
			} else {
				throw new CakeException(__('Could not get OAuthV2 Access Token from %s', $apiName));
			}
		} elseif ($method == 'OAuth') {
			$verifier = $this->controller->request->query('oauth_verifier');
			if (empty($verifier)) {
				throw new CakeException(__('Oauth verification code for API %s not found.', $apiName));
			}
			if (!$this->Session->check("Oauth.$apiName.request_token")) {
				throw new CakeException(__('Request token for API %s not found in Session.', $apiName));
			}
			$auth = array('oauth_verifier' => $verifier);
			$authVars = array_merge($auth, $this->Session->read("Oauth.$apiName.request_token"));
			$accessToken = $this->getAccessToken($apiName, $authVars);
			if (!empty($accessToken)) {
				return $this->_afterRequest($accessToken, $apiName, $method);
			} else {
				throw new CakeException(__('Could not get OAuth Access Token from %s', $apiName));
			}
		}
	}

	protected function _afterRequest(array $accessToken, $apiName, $version) {
		if ($this->store($accessToken, $apiName, $version)) {
			if ($this->Session->check('Oauth.redirect')) {
				$redirect = $this->Session->read('Oauth.redirect');
				$this->Session->delete('Oauth.redirect');
				$this->controller->redirect($redirect);
			} else {
				return $accessToken;
			}
		} else {
			throw new CakeException(__('Could not store access token for API %s', $apiName));
		}
	}

	/**
	 *
	 * @param string $apiName
	 * @param string|array $accessToken
	 * @param string $tokenSecret
	 */
	public function store(array $accessToken, $apiName, $version) {
		$storageMethod = (empty($this->controller->Apis[$apiName]['store'])) ? 'Db' : ucfirst($this->controller->Apis[$apiName]['store']);
		$Store = ClassRegistry::init('Copula.TokenStore' . $storageMethod);
		if ($Store instanceof TokenStoreInterface) {
			return $Store->saveToken($accessToken, $apiName, AuthComponent::user('id'), $version);
		} else {
			throw new CakeException(__('Storage Method: %s not supported.', $storageMethod));
		}
	}

}

?>