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

	var $components = array('Auth');
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

	function setUp() {
		parent::setUp();
		$Request = new CakeRequest();
		$Response = new CakeResponse();
		$collection = new ComponentCollection();
		$settings = array('Apis' => array('testapi' => array('store' => 'Db')));
		$this->controller = new TestController($Request, $Response);
		$this->Oauth = new OauthComponent($collection, $settings);
		OauthCredentials::reset();
		ConnectionManager::create('testapi', array(
			'datasource' => 'Apis.ApisSource',
			'login' => 'login',
			'password' => 'password',
			'authMethod' => 'OAuthV2',
		));
		$oauth = array('scheme' => 'https',
			'version' => '2.0',
			'authorize' => 'auth',
			'access' => 'token',
			'host' => 'accounts.example.com/oauth2',
			'scope' => 'https://www.example.com/auth/',
			'callback' => 'https://localhost.local/oauth2callback');
		Configure::write('Apis.testapi.oauth', $oauth);
		$this->Oauth->initialize($this->controller);
	}

	function tearDown() {
		OauthCredentials::reset();
		Configure::delete('Apis.testapi.oauth');
		ConnectionManager::drop('testapi');
		unset($this->controller);
		parent::tearDown();
	}

	function testConstruct() {
		$this->assertTrue($this->Oauth->Http instanceof HttpSocketOauth);
		$this->assertTrue($this->Oauth->controller instanceof TestController);
	}

	function testAuthorize() {
		$this->Oauth->authorize('testapi', 'oauthToken');
		$expected = "https://accounts.example.com/oauth2/auth?oauth_token=oauthToken";
		$this->assertEquals($expected, $this->controller->redirect);
	}

	function testAuthorizeV2() {
		$this->Oauth->authorizeV2('testapi');
		$expected = "https://accounts.example.com/oauth2/auth?redirect_uri=https%3A%2F%2Flocalhost.local%2Foauth2callback&client_id=login&scope=https%3A%2F%2Fwww.example.com%2Fauth%2F";
		$this->assertEquals($expected, $this->controller->redirect);
	}

	function testGetAccessToken() {
		unset($this->Oauth->Http);
		$this->Oauth->Http = $this->getMock('HttpSocket');
		$response = new HttpSocketResponse();
		$response->code = 200;
		$response->body = 'This is a test response body. What did you expect?';
		$authVars = array(
			'oauth_consumer_key' => 'key',
			'oauth_consumer_secret' => 'secret',
			'oauth_verifier' => 'verifier',
			'oauth_token' => 'requestToken',
			'oauth_token_secret' => 'requestTokenSecret'
		);
		$request = array(
			'method' => 'GET',
			'uri' => array(
				'host' => 'accounts.example.com/oauth2',
				'path' => 'token',
				'scheme' => 'https'
			),
			'auth' => $authVars
		);
		$this->Oauth->Http->expects($this->any())
				->method('request')
				->will($this->returnValueMap(array(array($request, $response))));
		$result = $this->Oauth->getAccessToken('testapi', $authVars);
		$this->assertEquals($response->body, $result);
	}

	function testGetAccessTokenV2() {
		unset($this->Oauth->Http);
		$this->Oauth->Http = $this->getMock('HttpSocket');
		$response = new HttpSocketResponse();
		$response->code = 200;
		$response->body = 'This is a test response body. What did you expect?';
		$request = array(
			'method' => 'POST',
			'uri' => array(
				'host' => 'accounts.example.com/oauth2',
				'path' => 'token',
				'scheme' => 'https'
			),
			'body' => array(
				'client_id' => 'login',
				'client_secret' => 'password',
				'code' => 'I am a banana!'
			)
		);
		$this->Oauth->Http->expects($this->any())
				->method('request')
				->will($this->returnValueMap(array(array($request, $response))));
		$token = 'I am a banana!';
		$result = $this->Oauth->getAccessTokenV2('testapi', $token);
		$this->assertEquals($response->body, $result);
	}

	function testGetOauthRequestToken(){
		unset($this->Oauth->Http);
		$this->Oauth->Http = $this->getMock('HttpSocket');
		$response = new HttpSocketResponse();
		$response->code = 200;
		$response->body = 'This is a test response body. What did you expect?';
		$request = array(
			'method' => 'GET',
			'uri' => array(
				'host' => 'accounts.example.com/oauth2',
				'path' => 'request',
				'scheme' => 'https',
				'query' =>'scope=https%3A%2F%2Fwww.example.com%2Fauth%2F'
			),
			'auth' => array(
				'oauth_consumer_key' => 'login',
				'oauth_consumer_secret' => 'password',
				'oauth_callback' => "https://localhost.local/oauth2callback"
			)
		);
		$this->Oauth->Http->expects($this->any())
				->method('request')
				->will($this->returnValueMap(array(array($request, $response))));
		Configure::write('Apis.testapi.oauth.request', 'request');
		Configure::write('Apis.testapi.oauth.version', '1.0');
		$result = $this->Oauth->getOauthRequestToken('testapi');
		$this->assertEquals($response->body, $result);
	}
	
}

?>