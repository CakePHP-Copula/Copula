<?php

App::uses('CakeSession', 'Model/Datasource');
App::uses('TokenStoreSession', 'Copula.Model');
App::uses('HttpSocketOauth', 'Copula.Network/Http');
App::uses('HttpSocketResponse', 'Network/Http');

/**
 * @property TokenStoreSession $Token
 */
class TokenStoreSessionTest extends CakeTestCase {

	public $tokenV1 = array(
		'api' => 'testapi',
		'user_id' => '42',
		'oauth_token' => 'canHas',
		'oauth_token_secret' => 'canHasSekrit'
	);
	public $tokenV2 = array(
		'api' => 'testapi',
		'user_id' => '42',
		'access_token' => 'canHas',
		'refresh_token' => 'canHasRefresh',
		'expires_in' => '3600'
	);

	public function setUp() {
		parent::setUp();
		$this->Token = ClassRegistry::init('Copula.TokenStoreSession');
	}

	public function testGetToken() {
		$token = $this->tokenV2;
		$token['modified'] = date('Y-m-d H:i:s');
		CakeSession::write('Copula.testapi.42', $token);
		$this->assertEquals($token, $this->Token->getToken('42', 'testapi'));
	}

	public function testGetTokenRefresh() {
		$config = array(
			'datasource' => 'Copula.RemoteTokenSource',
			'login' => 'login',
			'password' => 'password',
			'authMethod' => 'OAuthV2',
			'access' => 'token',
			'scheme' => 'https',
			'host' => 'accounts.example.com'
		);
		ConnectionManager::create('testapiToken', $config);
		$testapi = ConnectionManager::getDataSource('testapiToken');
		$testapi->Http = $this->getMock('HttpSocketOauth');
		$refresh = new HttpSocketResponse();
		$refresh->code = 200;
		$body = array('access_token' => 'token', 'refresh_token' => 'refresh', 'expires_in' => '1234');
		$refresh->body = json_encode($body);
		$refresh->headers['Content-Type'] = 'application/json';
		$request = array(
			'method' => 'POST',
			'uri' => array(
				'host' => 'accounts.example.com',
				'path' => 'token',
				'scheme' => 'https'
			),
			'body' => array(
				'client_id' => 'login',
				'client_secret' => 'password',
				'grant_type' => 'refresh_token',
				'refresh_token' => 'canHasRefresh'
			)
		);
		$testapi->Http->expects($this->once())
				->method('request')
				->will($this->returnValueMap(array(array($request, $refresh))));
		$token = $this->tokenV2;
		$token['modified'] = '1970-01-01 00:00:00';
		CakeSession::write('Copula.testapi.42', $token);
		$result = $this->Token->getToken('42', 'testapi');
		$expected = array_merge($token, $body);
		$this->assertEquals($expected, $result);
		ConnectionManager::drop('testapiToken');
	}

	public function tearDown() {
		parent::tearDown();
	}

}

?>