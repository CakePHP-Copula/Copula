<?php

CakePlugin::load('HttpSocketOauth');
App::uses('Controller', 'Controller');
App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');
App::uses('HttpSocketResponse', 'Network/Http');
App::uses('HttpSocketOauth', 'Copula.Network/Http');
App::uses('ComponentCollection', 'Controller');
App::uses('SessionComponent', 'Controller/Component');
App::uses('OauthComponent', 'Copula.Controller/Component');

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

	var $fixtures = array('plugin.copula.tokenstoredb');

	function setUp() {
		parent::setUp();
		$Request = new CakeRequest();
		$Response = new CakeResponse();
		$collection = new ComponentCollection();
		$this->controller = new TestController($Request, $Response);
		$this->controller->Apis = array('testapi');
		$this->controller->constructClasses();
		$this->Oauth = new OauthComponent($collection);
		Configure::write('Copula.testapi.Auth', array(
			'authMethod' => 'OAuthV2',
			'scheme' => 'https',
			'host' => 'www.example.com/api',
			'authorize' => 'auth',
			'access' => 'token',
			'request' => 'request',
			'host' => 'accounts.example.com/oauth2',
			'scope' => 'https://www.example.com/auth/',
			'callback' => 'https://localhost.local/oauth2callback'
		));
		ConnectionManager::create('testapi', array(
			'datasource' => 'Copula.ApisSource',
			'login' => 'login',
			'password' => 'password'
		));
		$this->Oauth->initialize($this->controller);
		$this->Oauth->startup($this->controller);
		$this->Oauth->TokenSource->beforeQuery(array('api' => 'testapi'));
		$ds = ConnectionManager::getDataSource('testapiToken');
		$ds->Http = $this->getMock('HttpSocketOauth', array('request'));
	}

	function tearDown() {
		ConnectionManager::drop('testapi');
		ConnectionManager::drop('testapiToken');
		Configure::delete('Copula.testapi.Auth');
		unset($this->controller, $this->Oauth);
		parent::tearDown();
	}

	function testConstruct() {
		$this->assertTrue($this->Oauth->controller instanceof TestController);
	}

	function testAuthorize() {
		Configure::write('Copula.testapi.Auth.authMethod', 'OAuth');
		$results = $this->Oauth->authorize('testapi', 'oauthToken');
		$expected = "https://accounts.example.com/oauth2/auth?oauth_token=oauthToken";
		$this->assertEquals($expected, $results);
	}

	function testAuthorizeV2() {
		$results = $this->Oauth->authorizeV2('testapi');
		$expected = 'https://accounts.example.com/oauth2/auth?redirect_uri=https%3A%2F%2Flocalhost.local%2Foauth2callback&client_id=login&scope=https%3A%2F%2Fwww.example.com%2Fauth%2F&response_type=code&access_type=offline';
		$this->assertEquals($expected, $results);
	}

	function testBeforeRedirectNoAuto() {
		$this->Oauth->settings['autoAuth'] = false;
		$this->assertNull($this->Oauth->beforeRedirect($this->controller, 'someurl'));
	}

	function testBeforeRedirectNoFail() {
		$this->Oauth->settings['autoAuth'] = true;
		$this->assertNull($this->Oauth->beforeRedirect($this->controller, 'someurl'));
	}

	function testBeforeRedirectNoApis() {
		unset($this->controller->Apis);
		$this->assertNull($this->Oauth->beforeRedirect($this->controller, 'someurl'));
	}

	function testGetOAuthNoApiConfig() {
		$this->assertFalse($this->Oauth->getOauthUri('someapi', 'access'));
	}

	function testbeforeRedirectV2() {
		$this->controller->Apis['testapi']['authorized'] = false;
		$this->Oauth->beforeRedirect($this->controller, 'some_url');
		$expected = "https://accounts.example.com/oauth2/auth?redirect_uri=https%3A%2F%2Flocalhost.local%2Foauth2callback&client_id=login&scope=https%3A%2F%2Fwww.example.com%2Fauth%2F&response_type=code&access_type=offline";
		$this->assertEquals($expected, $this->controller->redirect);
		$this->assertEmpty($this->controller->Apis['testapi']);
	}

	function testConnectV1() {
		$this->controller->Apis['testapi']['authorized'] = false;
		$ds = ConnectionManager::getDataSource('testapiToken');
		Configure::write('Copula.testapi.Auth.authMethod', 'OAuth');
		$ds->setConfig(array('authMethod' => 'OAuth'));
		$response = new HttpSocketResponse();
		$response->code = 200;
		$response->body = http_build_query(array('oauth_token' => 'abcdef', 'oauth_token_secret' => 'TheTruthIsOutThere'));
		$ds->Http->expects($this->once())
				->method('request')
				->will($this->returnValue($response));
		$this->Oauth->beforeRedirect($this->controller, 'someurl');
		$expected = "https://accounts.example.com/oauth2/auth?oauth_token=abcdef";
		$this->assertEquals($expected, $this->controller->redirect);
		$this->assertEmpty($this->controller->Apis['testapi']);
	}

	function testCallbackV2() {
		CakeSession::delete('Oauth.redirect');
		$this->controller->request->query = array('code' => 'tanstaafl');
		$ds = ConnectionManager::getDataSource('testapiToken');
		$ds->Http = $this->getMock('HttpSocketOauth', array('request'));
		$response = new HttpSocketResponse();
		$response->code = 200;
		$response->body = json_encode(array('access_token' => 'sayThe', 'refresh_token' => 'magicWord', 'type' => 'bearer', 'expires_in' => '3600'));
		$response->headers['Content-Type'] = 'application/json; charset utf-8';
		$ds->Http->expects($this->once())
				->method('request')
				->will($this->returnValue($response));
		CakeSession::write('Auth.User.id', '42');
		$token = $this->Oauth->callback('testapi');
		$this->assertEquals(array(
			'access_token' => 'sayThe',
			'refresh_token' => 'magicWord',
			'type' => 'bearer',
			'expires_in' => '3600'), $token);
		CakeSession::delete('Auth.User.id');
	}

	function testCallbackV1() {
		$ds = ConnectionManager::getDataSource('testapiToken');
		Configure::write('Copula.testapi.Auth.authMethod', 'OAuth');
		ConnectionManager::getDataSource('testapiToken')->setConfig(array('authMethod' => 'OAuth'));
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
		$this->Oauth->Session->expects($this->any())
				->method('check')
				->will($this->onConsecutiveCalls(true, false));
		CakeSession::write('Auth.User.id', '42');
		$token = $this->Oauth->callback('testapi');
		$expected = array(
			'oauth_token' => 'abcdef',
			'oauth_token_secret' => 'TheTruthIsOutThere');
		$this->assertEquals($expected, $token);
		CakeSession::delete('Auth.User.id');
	}

	public function testCallbackV2NoCodeException() {
		$this->expectException('CakeException', 'Authorization token for API noApi not received.');
		$collect = new ComponentCollection();
		$this->Oauth = $this->getMock('OauthComponent', array('getOauthMethod'), array($collect));
		$this->Oauth->expects($this->once())
				->method('getOauthMethod')
				->will($this->returnValue('OAuthV2'));
		$this->Oauth->controller->request = new CakeRequest();
		$this->Oauth->callback('noApi');
	}

	public function testCallbackV1NoCodeException() {
		$collect = new ComponentCollection();
		$this->Oauth = $this->getMock('OauthComponent', array('getOauthMethod'), array($collect));
		$this->Oauth->expects($this->once())
				->method('getOauthMethod')
				->will($this->returnValue('OAuth'));
		$this->Oauth->controller->request = new CakeRequest();
		$this->expectException('CakeException', 'Oauth verification code for API noApi not found.');
		$this->Oauth->callback('noApi');
	}

	public function testCallbackV1NoRequestTokenException() {
		$collect = new ComponentCollection();
		$this->Oauth = $this->getMock('OauthComponent', array('getOauthMethod'), array($collect));
		$this->Oauth->expects($this->once())
				->method('getOauthMethod')
				->will($this->returnValue('OAuth'));
		$this->Oauth->controller->request = new CakeRequest();
		$this->Oauth->controller->request->query = array('oauth_verifier' => 'verified');
		$this->expectException('CakeException', 'Request token for API noApi not found in Session.');
		$this->Oauth->callback('noApi');
	}

	public function testCallbackV2NoTokenException() {
		$this->expectException('CakeException', 'Could not get OAuthV2 Access Token from noApi');
		$collect = new ComponentCollection();
		$this->Oauth = $this->getMock('OauthComponent', array('getOauthMethod', 'getAccessTokenV2'), array($collect));
		$this->Oauth->expects($this->once())
				->method('getOauthMethod')
				->will($this->returnValue('OAuthV2'));
		$this->Oauth->controller->request = new CakeRequest();
		$this->Oauth->controller->request->query = array('code' => 'accessCode');
		$this->Oauth->expects($this->once())
				->method('getAccessTokenV2')
				->will($this->returnValue(false));
		$this->Oauth->callback('noApi');
	}

	public function testAfterRequestRedirect() {
		$this->controller->request->query = array('code' => 'tanstaafl');
		$ds = ConnectionManager::getDataSource('testapiToken');
		$ds->Http = $this->getMock('HttpSocketOauth', array('request'));
		$response = new HttpSocketResponse();
		$response->code = 200;
		$response->body = json_encode(array('access_token' => 'sayThe', 'refresh_token' => 'magicWord', 'type' => 'bearer', 'expires_in' => '3600'));
		$response->headers['Content-Type'] = 'application/json; charset utf-8';
		$ds->Http->expects($this->once())
				->method('request')
				->will($this->returnValue($response));
		CakeSession::write('Auth.User.id', '42');
		CakeSession::write('Oauth.redirect', 'someurl');
		$this->Oauth->callback('testapi');
		$this->assertEquals('someurl', $this->Oauth->controller->redirect);
		CakeSession::delete('Auth.User.id');
		CakeSession::delete('Oauth.redirect');
	}

	public function testAfterRequestNoStoreException() {
		$collect = new ComponentCollection();
		$this->Oauth = $this->getMock('OauthComponent', array('store'), array($collect));
		$this->Oauth->expects($this->once())
				->method('store')
				->will($this->returnValue(false));
		$this->Oauth->controller->request = new CakeRequest();
		$this->Oauth->controller->request->query = array('code' => 'accessCode');
		$ds = ConnectionManager::getDataSource('testapiToken');
		$ds->Http = $this->getMock('HttpSocketOauth', array('request'));
		$response = new HttpSocketResponse();
		$response->code = 200;
		$response->body = json_encode(array('access_token' => 'sayThe', 'refresh_token' => 'magicWord', 'type' => 'bearer', 'expires_in' => '3600'));
		$response->headers['Content-Type'] = 'application/json; charset utf-8';
		$ds->Http->expects($this->once())
				->method('request')
				->will($this->returnValue($response));
		$this->expectException('CakeException', 'Could not store access token for API testapi');
		$this->Oauth->callback('testapi');
	}

	public function testStoreException() {
		$this->Oauth->controller->Apis = array('testapi' => array('store' => 'safeway'));
		$this->expectException('CakeException', 'Storage Method: Safeway not supported.');
		$this->Oauth->store(array(), 'testapi', 'OAuthV2');
	}

}

?>