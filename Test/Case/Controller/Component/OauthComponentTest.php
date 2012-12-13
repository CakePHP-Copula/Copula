<?php

CakePlugin::load('HttpSocketOauth');
App::uses('Controller', 'Controller');
App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');
App::uses('HttpSocketResponse', 'Network/Http');
App::uses('HttpSocketOauth', 'HttpSocketOauth.Lib');
App::uses('ComponentCollection', 'Controller');
App::uses('OauthComponent', 'Apis.Controller/Component');

class TestController extends Controller {

	var $components = array('Auth' => array(
			'authorize' => array('Apis' => array('testapi' => array('store' => 'Db')))
			));
	var $autoRender = false;

	function redirect($url) {
		$this->redirect = $url;
	}

	function render($action = null, $layout = null, $file = null) {
		$this->renderedAction = $action;
	}

	function _stop($status = 0) {
		$this->stopped = $status;
	}

}

/**
 * @property TestController $controller
 * @property OauthComponent $Oauth
 */
class OauthComponentTest extends CakeTestCase {

	var $fixtures = array('plugin.apis.token');

	function setUp() {
		parent::setUp();
		$Request = new CakeRequest();
		$Response = new CakeResponse();
		$collection = new ComponentCollection();
		$this->controller = new TestController($Request, $Response);
		$this->controller->constructClasses();
		$this->Oauth = new OauthComponent($collection);
		ConnectionManager::create('testapi', array(
			'datasource' => 'Apis.ApisSource',
			'login' => 'login',
			'password' => 'password',
			'authMethod' => 'OAuthV2',
			'scheme' => 'https',
			'host' => 'www.example.com/api'
		));
		ConnectionManager::create('testapiToken', array(
			'datasource' => 'Apis.RemoteTokenSource',
			'login' => 'login',
			'password' => 'password',
			'authMethod' => 'OAuthV2',
			'scheme' => 'https',
			'authorize' => 'auth',
			'access' => 'token',
			'request' => 'request',
			'host' => 'accounts.example.com/oauth2',
			'scope' => 'https://www.example.com/auth/',
			'callback' => 'https://localhost.local/oauth2callback'));
		$ds = ConnectionManager::getDataSource('testapiToken');
		$ds->Http = $this->getMock('HttpSocketOauth', array('request'));
		$this->Oauth->initialize($this->controller);
	}

	function tearDown() {
		ConnectionManager::drop('testapi');
		ConnectionManager::drop('testapiToken');
		unset($this->controller, $this->Oauth);
		parent::tearDown();
	}

	function testConstruct() {
		$this->assertTrue($this->Oauth->controller instanceof TestController);
	}

	function testAuthorize() {
		ConnectionManager::getDataSource('testapiToken')->setConfig(array('authMethod' => 'OAuth'));
		$this->Oauth->authorize('testapi', 'oauthToken');
		$expected = "https://accounts.example.com/oauth2/auth?oauth_token=oauthToken";
		$this->assertEquals($expected, $this->controller->redirect);
	}

	function testAuthorizeV2() {
		$this->Oauth->authorizeV2('testapi');
		$expected = "https://accounts.example.com/oauth2/auth?redirect_uri=https%3A%2F%2Flocalhost.local%2Foauth2callback&client_id=login&scope=https%3A%2F%2Fwww.example.com%2Fauth%2F";
		$this->assertEquals($expected, $this->controller->redirect);
	}

	/*
	  function testGetAccessToken() {
	  ConnectionManager::getDataSource('testapi')->setConfig(array('authMethod' => 'OAuth'));
	  $result = $this->Oauth->getAccessToken('testapi', $authVars);
	  $this->assertInternalType('array', $result);
	  }

	  function testGetAccessTokenV2() {
	  $result = $this->Oauth->getAccessTokenV2('testapi', $token);
	  $this->assertEquals('This is a test response body. What did you expect?', $result);
	  }

	  function testGetOauthRequestToken() {
	  $result = $this->Oauth->getOauthRequestToken('testapi');
	  $this->assertInternalType('array', $result);
	  }
	 */

	function testConnectV2() {
		$this->Oauth->connect('testapi');
		$expected = "https://accounts.example.com/oauth2/auth?redirect_uri=https%3A%2F%2Flocalhost.local%2Foauth2callback&client_id=login&scope=https%3A%2F%2Fwww.example.com%2Fauth%2F";
		$this->assertEquals($expected, $this->controller->redirect);
	}

	function testConnectV1() {
		$ds = ConnectionManager::getDataSource('testapiToken');
		$ds->setConfig(array('authMethod' => 'OAuth'));
		$response = new HttpSocketResponse();
		$response->code = 200;
		$response->body = http_build_query(array('oauth_token' => 'abcdef', 'oauth_token_secret' => 'TheTruthIsOutThere'));
		$ds->Http->expects($this->once())
				->method('request')
				->will($this->returnValue($response));
		$this->Oauth->connect('testapi');
		$expected = "https://accounts.example.com/oauth2/auth?oauth_token=abcdef";
		$this->assertEquals($expected, $this->controller->redirect);
	}

	function testCallbackV2() {
		$this->controller->request->query = array('code' => 'tanstaafl');
		$ds = ConnectionManager::getDataSource('testapiToken');
		$ds->Http = $this->getMock('HttpSocketOauth', array('request'));
		$response = new HttpSocketResponse();
		$response->code = 200;
		$response->body = json_encode(array('access_token' => 'sayThe', 'refresh_token' => 'magicWord', 'type' => 'bearer', 'expires' => '3600'));
		$response->headers['Content-Type'] = 'application/json; charset utf-8';
		$ds->Http->expects($this->once())
				->method('request')
				->will($this->returnValue($response));
		$this->Oauth->callback('testapi');
		$this->assertEquals(array('access_token' => 'sayThe', 'refresh_token' => 'magicWord'), OauthConfig::getAccessToken('testapi'));
	}

	function testCallbackV1() {
		$ds = ConnectionManager::getDataSource('testapiToken');
		$ds->setConfig(array('authMethod' => 'OAuth'));
		ConnectionManager::getDataSource('testapi')->setConfig(array('authMethod' => 'OAuth'));
		$response = new HttpSocketResponse();
		$response->code = 200;
		$response->body = http_build_query(array('oauth_token' => 'abcdef', 'oauth_token_secret' => 'TheTruthIsOutThere'));
		$ds->Http->expects($this->once())
				->method('request')
				->will($this->returnValue($response));
		$this->controller->request->query = array('oauth_verifier' => 'tanstaafl');
		$this->Oauth->Session = $this->getMock('SessionComponent', array('read', 'check'), array($this->controller->Components));
		$requestToken = array('oauth_token' => 'token',
			'oauth_token_secret' => 'secret');
		$this->Oauth->Session->expects($this->once())
				->method('read')
				->will($this->returnValue($requestToken));
		$this->Oauth->Session->expects($this->once())
				->method('check')
				->will($this->returnValue(true));
		$token = $this->Oauth->callback('testapi');
		$this->assertEquals($token,OauthConfig::getAccessToken('testapi'));
	}

}

?>