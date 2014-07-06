<?php

App::uses('HttpSource', 'Copula.Model/Datasource');
App::uses('Token', 'Copula.Model');
App::uses('SoapRequest', 'Copula.Network/Http');

class HttpSourceTest extends CakeTestCase {

	public function setUp() {
		parent::setUp();
		$config = array(
			'datasource' => 'Copula.HttpSource',
			'login' => 'abc123',
			'password' => 'password'
		);
		$this->Source = $this->getMock('HttpSource', array('send'), array($config));
		$this->Source->map = array(
			'examplepath/endpoint.json' => array(
				'GET' => array(
					'name' => 'alphaNumeric',
					'date' => 'date'
				)
			)
		);
		$this->Source->baseUrl = 'http://example.com/';
	}

	public function tearDown() {
		unset($this->Source);
		parent::tearDown();
	}

	public function testDescribe() {
		$model = ClassRegistry::init('AppModel');
		$model->useTable = 'examplepath/endpoint.json';
		$expected = array('name', 'date');
		$result = $this->Source->describe($model);
		$this->assertEquals($expected, $result);
	}

	public function testListSources() {
		$expected = array('examplepath/endpoint.json');
		$result = $this->Source->listSources();
		$this->assertEquals($expected, $result);
	}

	public function testCalculate() {
		$result = $this->Source->calculate(null, null, array());
		$this->assertEquals('COUNT', $result);
	}

	public function testIsAuthorizedBasic() {
		$model = ClassRegistry::init('AppModel');
		$result = $this->Source->isAuthorized($model, array(), array());
		$this->assertTrue($result);
	}

	public function testIsAuthorizedToken() {
		$TokenSource = $this->getMock('HttpSource', array('request'));
		$TokenSource->expects($this->once())
			->method('request')
			->will($this->returnValue('C0FFEEC0FFEE'));
		$TokenSource->map = array(
			'authorize' => array(
				'GET' => array()
			)
		);
		$Token = ClassRegistry::init('Copula.Token');
		$Token = $this->getMock('Token', array('getDataSource', 'isExpired'), array(false, false, 'test'));
		$Token->useTable = 'authorize';
		$Token->expects($this->any())
			->method('getDataSource')
			->will($this->returnValue($TokenSource));
		$Token->expects($this->exactly(2))
			->method('isExpired')
			->will($this->onConsecutiveCalls(true, false));
		$this->Source->Token = $Token;
		$this->Source->config['authorize'] = array(
			'type' => 'Token'
		);
		$response = new HttpSocketResponse();
		$response->code = 200;
		$response->body = json_encode(array('success' => true));
		$response->headers['Content-Type'] = 'application/json; charset UTF-8';
		$this->Source->expects($this->once())
			->method('send')
			->with($this->isInstanceOf('HttpSocket'), $this->isInstanceOf('Request'))
			->will($this->returnValue($response));
		$model = ClassRegistry::init('AppModel');
		$model->useTable = 'examplepath/endpoint.json';
		$result = $this->Source->request($model, 'GET');
		$this->assertEquals(array('success' => true ), $result);
	}

	public function testBuildRequest() {
		$method = 'GET';
		$endpoint = 'examplepath/endpoint.json';
		$queryData = array('conditions' =>array('foo' => 'bar', 'baz' => 'qux'));
		$postData = array();
		$result = $this->Source->buildRequest($method, $endpoint, $queryData, $postData);
		$this->assertInstanceOf('Request', $result);
		$url = $result->url();
		$this->assertArrayHasKey('query', $url);
		$this->assertEquals($endpoint, $url['path']);
		$this->assertEquals($method, $result->method());
		$this->assertEquals('example.com', $url['host']);
		$this->assertEquals($queryData['conditions'], $url['query']);
	}

	public function testBuildSoapRequest() {
		$method = 'Login';
		$endpoint = 'Login';
		$queryData = array();
		$postData = array('Username' => 'alpha', 'Password' => 'beta');
		$this->Source->map = array(
			'Login' => array(
				'Login' => array(
					'Username' => array('rule' => 'alphaNumeric', 'required' => true),
					'Password' => array('rule' => 'alphaNumeric', 'required' => true)
				)
			)
		);
		$this->Source->config['requestObject'] = 'SoapRequest';
		$result = $this->Source->buildRequest($method, $endpoint, $queryData, $postData);
		$this->assertInstanceOf('SoapRequest', $result);
		$url = $result->url();
		$this->assertEquals($endpoint, $url['path']);
		$this->assertEquals($method, $result->method());
		$this->assertEquals('example.com', $url['host']);
		$this->assertEquals($postData, $result->body());
	}

	public function testHttpBasicHeaders() {
		$method = 'GET';
		$endpoint = 'examplepath/endpoint.json';
		$this->Source->config['authorize'] = 'Basic';
		$creds = array('login' => 'abc', 'password' => '123');
		$this->Source->setConfig($creds);
		$result = $this->Source->buildRequest($method, $endpoint, array(), array());
		$authHeader = $result->header('Auth');
		$this->assertArrayHasKey('user', $authHeader);
		$this->assertArrayHasKey('pass', $authHeader);
		$this->assertEquals('Basic', $authHeader['method']);
	}

	public function testOauthv1Headers() {
		$method = 'GET';
		$endpoint = 'examplepath/endpoint.json';
		$this->Source->config['authorize'] = 'OAuth';
		$creds = array(
			'login' => 'oauth_consumer_key',
			'password' => 'oauth_consumer_secret',
			'access_token' => '123456',
			'token_secret' => 'the bodies are buried under the'
		);
		$this->Source->setConfig($creds);
		$result = $this->Source->buildRequest($method, $endpoint, array(), array());
		$authHeader = $result->header('Auth');
		$expected = array(
			'method' => 'OAuth',
			'oauth_consumer_key' => 'oauth_consumer_key',
			'oauth_consumer_secret' => 'oauth_consumer_secret',
			'oauth_token' => '123456',
			'oauth_token_secret' => 'the bodies are buried under the'
		);
		$this->assertEquals($expected, $authHeader);
	}

	public function testOauthv2Headers() {
		$method = 'GET';
		$endpoint = 'examplepath/endpoint.json';
		$this->Source->config['authorize'] = 'OAuthV2';
		$creds = array(
			'login' => 'client_id',
			'password' => 'client_secret',
			'access_token' => '123456',
		);
		$this->Source->setConfig($creds);
		$result = $this->Source->buildRequest($method, $endpoint, array(), array());
		$authHeader = $result->header('Auth');
		$expected = array(
			'method' => 'OAuth',
			'oauth_version' => '2.0',
			'client_id' => 'client_id',
			'client_secret' => 'client_secret',
			'access_token' => '123456'
		);
		$this->assertEquals($expected, $authHeader);
	}


}