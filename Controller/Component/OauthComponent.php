<?php

App::uses('HttpSocketOauth', 'HttpSocketOauth.Lib');
App::uses('Token', 'Apis.Model');
App::uses('OauthCredentials', 'Apis.Lib');

/**
 * @property HttpSocketOauth $Http
 * @property Token $Token
 * @property Controller $controller
 */
class OauthComponent extends Component {

	public $components = array('Session');
	public $controller;
	public $userId;

	function __construct(\ComponentCollection $collection, $settings = array()) {
		$this->Http = new HttpSocketOauth();
		parent::__construct($collection, $settings);
	}

	function initialize(\Controller $controller) {
		$this->controller = $controller;
		$this->userId = AuthComponent::user('id');
	}

	/*
	  function beforeFilter() {
	  if (!$this->Auth->isAuthorized()) {
	  $authFlash = $this->controller->Auth->flash;
	  $authMessage = Session::read('Message.' . $authFlash['element']);
	  xdebug_break();
	  //$this->authorize($apiName);
	  }
	  } */

	/**
	 * 
	 * @param string $apiName
	 * @param string $requestToken
	 * @param array $requestOptions
	 */
	public function authorize($apiName, $requestToken, $requestOptions = array()) {
		$request = $this->_getRequest($apiName, 'authorize');
		$query = array('oauth_token' => $requestToken);
		$request['uri']['query'] = $this->_buildQuery($query);
		$request = Hash::merge($request, $requestOptions);
		$this->controller->redirect($this->Http->url($request['uri']));
	}

	/**
	 * 
	 * @param string $apiName
	 * @param array $requestOptions
	 */
	public function authorizeV2($apiName, $requestOptions = array()) {
		$request = $this->_getRequest($apiName, 'authorize');
		$config = Configure::read("Apis.$apiName.oauth");
		$credentials = OauthCredentials::getCredentials($apiName);
		$query = array(
			'redirect_uri' => $config['callback'],
			'client_id' => $credentials['key']
		);
		if (!empty($config['scope'])) {
			$query['scope'] = $config['scope'];
		}
		$request['uri']['query'] = $this->_buildQuery($query);
		$request = Hash::merge($request, $requestOptions);
		$this->controller->redirect($this->Http->url($request['uri']));
	}

	/**
	 * 
	 * @param string $apiName
	 * @param array $authVars
	 * @param array $requestOptions
	 * @return type
	 */
	public function getAccessToken($apiName, array $authVars, $requestOptions = array()) {
		$request = $this->_getRequest($apiName, 'access');
		$request['auth'] = array_merge($request['auth'], $authVars);
		$request = Hash::merge($request, $requestOptions);
		$response = $this->Http->request($request);
		if (!$response->isOk()) {
			return false;
		}
                parse_str($response->body(), $accessToken);
		return $accessToken;
	}

	/**
	 * 
	 * @param string $apiName
	 * @param string $token
	 * @param array $requestOptions
	 * @return boolean
	 */
	public function getAccessTokenV2($apiName, $token, $requestOptions = array()) {
		$request = $this->_getRequest($apiName, 'access');
		$request['method'] = 'POST';
		$request['body'] = array(
			'client_id' => $request['auth']['client_id'],
			'client_secret' => $request['auth']['client_secret'],
			'code' => $token
		);
                unset($request['auth']);
		$request = Hash::merge($request, $requestOptions);
		$response = $this->Http->request($request);
		if (!$response->isOk()) {
			return false;
		}
		return json_decode($response->body(), true);
	}

	/**
	 * 
	 * @param string $apiName
	 * @param array $requestOptions
	 * @return boolean|string
	 */
	function getOauthRequestToken($apiName, $requestOptions = array()) {
		$request = $this->_getRequest($apiName, 'request');
		$request['auth']['oauth_callback'] = Configure::read("Apis.$apiName.oauth.callback");
		$scope = Configure::read("Apis.$apiName.oauth.scope");
		if (!empty($scope)) {
			$request['uri']['query'] = $this->_buildQuery(array('scope' => $scope));
		}
		$request = Hash::merge($request, $requestOptions);
		$response = $this->Http->request($request);
		if (!$response->isOk()) {
			return false;
		}
                parse_str($response->body(), $requestToken);
		return $requestToken;
	}

	/**
	 * 
	 * @param string $apiName
	 */
	function connect($apiName) {
		$method = Configure::read("Apis.$apiName.oauth.version");
		if ($method == '2.0') {
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
	}

	/**
	 * 
	 * @param string $apiName
	 * @throws CakeException
	 */
	function callback($apiName) {
		$method = Configure::read("Apis.$apiName.oauth.version");
		if ($method == '2.0') {
			if (empty($this->controller->request->query['code'])) {
				throw new CakeException(__("Authorization token for API %s not received.", $apiName));
			}
			$oAuthCode = $this->controller->request->query['code'];
			$accessToken = $this->getAccessTokenV2($apiName, $oAuthCode);
			$this->store($apiName, $accessToken);
			return $accessToken;
		} elseif ($method == '1.0') {
			if (empty($this->controller->request->query['oauth_verifier'])) {
				throw new CakeException(__("OAuth verification code for API %s not found.", $apiName));
			}
			if (!$this->Session->check("Oauth.$apiName.request_token")) {
				throw new CakeException(__("Request token for API %s not found in Session.", $apiName));
			}
			$credentials = OauthCredentials::getCredentials($apiName);
			$auth = array(
				'oauth_consumer_key' => $credentials['key'],
				'oauth_consumer_secret' => $credentials['secret'],
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
	 * @param string $path
	 * @return array 
	 * @throws CakeException
	 */
	protected function _getRequest($apiName, $path) {
		$credentials = OauthCredentials::getCredentials($apiName);
		$config = Configure::read('Apis.' . $apiName . '.oauth');
		if (empty($config[$path])) {
			throw new CakeException(__("Missing oauth config for %s.", $path));
		}
		$request = array(
			'method' => 'GET',
			'uri' => array(
				'host' => $config['host'],
				'path' => $config[$path],
				'scheme' => $config['scheme']
			),
			'auth' => array(
				'method' => (($config['version'] == '2.0') ? 'OAuthV2' : 'OAuth'),
			)
		);
		if ($request['auth']['method'] == 'OAuth') {
			$request['auth']['oauth_consumer_key'] = $credentials['key'];
			$request['auth']['oauth_consumer_secret'] = $credentials['secret'];
		} elseif ($request['auth']['method'] == 'OAuthV2') {
			$request['auth']['client_id'] = $credentials['key'];
			$request['auth']['client_secret'] = $credentials['secret'];
		}
		return $request;
	}

	protected function _buildQuery($query, $escape = false) {
		if (is_array($query)) {
			$query = substr(Router::queryString($query, array(), $escape), '1');
		}
		return $query;
	}

	/**
	 * 
	 * @param string $apiName
	 * @param string|array $accessToken
	 * @param string $tokenSecret
	 */
	public function store($apiName, $accessToken, $tokenSecret = null) {
		$storageMethod = $this->controller->Auth->authorize['Apis'][$apiName]['store'];
		if (is_array($accessToken) && empty($tokenSecret)) {
			$data = array(
				'access_token' => $accessToken['access_token'],
				'refresh_token' => $accessToken['refresh_token']
			);
			OauthCredentials::setAccessToken($apiName, $data['access_token']);
		} else {
			$data = array(
				'oauth_token' => $accessToken,
				'oauth_token_secret' => $tokenSecret
			);
			OauthCredentials::setAccessToken($apiName, $data['oauth_token'], $data['oauth_token_secret']);
		}
		switch ($storageMethod) {
			case 'Session':
				$this->Session->write("Oauth.$apiName", $data);
				break;
			default:
				$this->Token = ClassRegistry::init('Apis.Token');
				return $this->Token->saveTokenDb($data, $apiName, $this->userId);
				break;
		}
	}

}

?>