<?php

App::uses('RemoteTokenSource', 'Copula.Model/Datasource');
App::uses('HttpSocketOauth', 'Copula.Network/Http');
App::uses('Model', 'Model');
App::uses('HttpSocketResponse', 'Network/Http');

/**
 * @property RemoteTokenSource $Source
 */
class RemoteTokenSourceTest extends CakeTestCase {

	public function setUp() {
		parent::setUp();
		$config = array(
			'datasource' => 'Copula.RemoteTokenSource',
			'login' => 'login',
			'password' => 'password',
			'authMethod' => 'OAuth',
			'scheme' => 'https',
			'authorize' => 'auth',
			'access' => 'token',
			'host' => 'accounts.example.com/oauth2',
			'scope' => 'https://www.exampleapis.com/auth/',
			'callback' => 'https://www.mysite.com/oauth2callback'
		);
		$this->Source = ConnectionManager::create('testapi', $config);
		$this->Source->Http = $this->getMock('HttpSocketOauth', array('request'));
		$this->Model = new Model();
	}

	public function tearDown() {
		ConnectionManager::drop('testapi');
		unset($this->Source, $this->Model);
		parent::tearDown();
	}

	public function testOauthV2Access() {
		ConnectionManager::getDataSource('testapi')->setConfig(array('authMethod' => 'OAuthV2'));
		$this->Model->findQueryType = 'access';
		$queryData = array('requestToken' => array('code' => 'code!', 'grantType' => 'access'));
		$request = array(
			'method' => 'POST',
			'uri' => array(
				'host' => 'accounts.example.com/oauth2',
				'path' => 'token',
				'scheme' => 'https'
			),
			'body' => "client_id=login&client_secret=password&grant_type=authorization_code&code=code%21&redirect_uri=https://www.mysite.com/oauth2callback"
		);
		$response = new HttpSocketResponse();
		$response->code = 200;
		$expected = array('access_token' => 'sayThe', 'refresh_token' => 'magicWord', 'type' => 'bearer', 'expires_in' => '3600');
		$response->body = json_encode($expected);
		$response->headers['Content-Type'] = 'application/json; charset utf-8';
		$this->Source->Http->expects($this->once())
				->method('request')
				->will($this->returnValueMap(array(array($request, $response))));
		$result = $this->Source->read($this->Model, $queryData);
		$this->assertEquals($result, $expected);
	}

	public function testOauthV1Access() {
		$this->Model->findQueryType = 'access';
		$authVars = array(
			'method' => 'OAuth',
			'oauth_consumer_key' => 'login',
			'oauth_consumer_secret' => 'password',
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
		$queryData = array('requestToken' => $authVars);
		$response = new HttpSocketResponse();
		$response->code = 200;
		$expected = array('token' => 'asian', 'token_secret' => 'notTelling');
		$response->body = http_build_query($expected);
		$this->Source->Http->expects($this->once())
				->method('request')
				->will($this->returnValueMap(array(array($request, $response))));
		$result = $this->Source->read($this->Model, $queryData);
		$this->assertEquals($expected, $result);
	}

	public function testOauthV1Request() {
		ConnectionManager::getDataSource('testapi')->setConfig(array(
			'request' => 'request_token',
			'scope' => ''
			));
		$this->Model->findQueryType = 'request';
		$queryData = array();
		$request = array(
			'method' => 'GET',
			'uri' => array(
				'host' => 'accounts.example.com/oauth2',
				'path' => 'request_token',
				'scheme' => 'https'
			),
			'auth' => array(
				'method' => 'OAuth',
				'oauth_consumer_key' => 'login',
				'oauth_consumer_secret' => 'password',
				'oauth_callback' => 'https://www.mysite.com/oauth2callback'
			)
		);
		$response = new HttpSocketResponse();
		$response->code = 200;
		$expected = array('oauth_token' => 'abcdef', 'oauth_token_secret' => 'TheTruthIsOutThere');
		$response->body = http_build_query($expected);
		$this->Source->Http->expects($this->once())
				->method('request')
				->will($this->returnValueMap(array(array($request, $response))));
		$result = $this->Source->read($this->Model, $queryData);
		$this->assertEquals($expected, $result);
	}

}

?>