<?php

App::uses('TokenSource', 'Apis.Model');
App::uses('TokenStoreDb', 'Apis.Model');
App::uses('OauthConfig', 'Apis.Lib');

/**
 * @property SessionComponent $Session
 * @property TokenSource $TokenSource
 * @property Controller $controller
 */
class OauthComponent extends Component {

	public $components = array('Session');
	public $controller;

	function __construct(\ComponentCollection $collection, $settings = array()) {
		$this->TokenSource = ClassRegistry::init('Apis.TokenSource');
		parent::__construct($collection, $settings);
	}

	function initialize(\Controller $controller) {
		$this->controller = $controller;
		$this->Apis = $this->controller->Apis;
	}

	function beforeFilter() {
		if (!$this->Auth->isAuthorized() && !empty($this->Apis)) {
			$usedApis = array_intersect($this->Apis, OauthConfig::getConfiguredApis());
			foreach ($usedApis as $apiName) {
				$token = OauthConfig::getAccessToken($apiName);
				if (empty($token)) {
					$this->Session->write('Oauth.redirect', $this->controller->request->here);
					$this->connect($apiName);
				}
			}
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
		$uri = OauthConfig::getAuthUri($apiName . 'Token', 'authorize', $requestOptions);
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
			'requestToken' => $token,
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
			if (empty($this->controller->request->query['code'])) {
				throw new CakeException("Authorization token for API $apiName not received.");
			}
			$oAuthCode = $this->controller->request->query['code'];
			$accessToken = $this->getAccessTokenV2($apiName, $oAuthCode);
			if (!empty($accessToken)) {
				$this->store($apiName, $accessToken);
				return $accessToken;
			} else {
				throw new CakeException(__('Could not get OAuth Access Token from %s', $apiName));
			}
		} elseif ($method == 'OAuth') {
			if (empty($this->controller->request->query['oauth_verifier'])) {
				throw new CakeException("Oauth verification code for API $apiName not found.");
			}
			if (!$this->Session->check("Oauth.$apiName.request_token")) {
				throw new CakeException("Request token for API $apiName not found in Session.");
			}
			$auth = array(
				'oauth_verifier' => $this->controller->request->query['oauth_verifier']
			);
			$authVars = array_merge($auth, $this->Session->read("Oauth.$apiName.request_token"));
			$accessToken = $this->getAccessToken($apiName, $authVars);
			if ($accessToken) {
				$this->store($apiName, $accessToken['oauth_token'], $accessToken['oauth_token_secret']);
				return $accessToken;
			} else {
				throw new CakeException(__('Could not get OAuth Access Token from %s', $apiName));
			}
		}
	}

	/**
	 * 
	 * @param string $apiName
	 * @param string|array $accessToken
	 * @param string $tokenSecret
	 */
	public function store($apiName, $accessToken, $tokenSecret = null) {
		$storageMethod = $this->controller->Apis[$apiName]['store'];
		if (is_array($accessToken) && empty($tokenSecret)) {
			$data = array(
				'access_token' => $accessToken['access_token'],
				'refresh_token' => $accessToken['refresh_token']
			);
			OauthConfig::setAccessToken($apiName, $data['access_token'], $data['refresh_token']);
		} else {
			$data = array(
				'oauth_token' => $accessToken,
				'oauth_token_secret' => $tokenSecret
			);
			OauthConfig::setAccessToken($apiName, $data['oauth_token'], $data['oauth_token_secret']);
		}
		switch ($storageMethod) {
			case 'Session':
				$this->Session->write("Oauth.$apiName", $data);
				break;
			default:
				$this->TokenStore = ClassRegistry::init('Apis.TokenStoreDb');
				return $this->TokenStore->saveToken($data, $apiName, AuthComponent::user('id'));
				break;
		}
	}

}

?>