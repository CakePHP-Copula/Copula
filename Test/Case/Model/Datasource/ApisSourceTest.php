<?php

App::uses('ApisSource', 'Copula.Model/Datasource');
App::uses('AppModel', 'Model');
App::uses('PhpReader', 'Configure');
App::uses('HttpSocketResponse', 'Network/Http');
App::uses('HttpSocket', 'Network/Http');
App::uses('OauthConfig', 'Copula.Lib');

class CopulaTestModel extends AppModel {

	var $useDbConfig = "testapi";
	var $useTable = 'section';
	var $schema = array(
		'id' => array(
			'type' => 'integer',
			'null' => false,
			'key' => 'primary',
			'length' => 11,
		),
		'text' => array(
			'type' => 'string',
			'null' => true,
			'length' => 140,
		),
		'status' => array(
			'type' => 'string',
			'null' => true,
			'length' => 140,
		),
		'customField' => array(
			'type' => 'string',
			'null' => true,
			'length' => 255,
		),
	);

}

/**
 * @property CopulaTestModel $model
 * @property ApisSource $Apis
 */
class ApisSourceTest extends CakeTestCase {

	var $request = array(
		'method' => 'GET',
		'uri' => array(
			'scheme' => 'https',
			'host' => 'www.example.com',
			'path' => '/auth/service',
			'query' => ''
		),
		'auth' => array(),
		'body' => '',
		'raw' => ''
	);
	var $config = array(
		'version' => '2.0',
		'scheme' => 'https',
		'authorize' => 'auth',
		'access' => 'token',
		'host' => 'accounts.example.com/oauth2',
		'scope' => 'https://www.exampleapis.com/auth/service',
		'callback' => 'https://www.test.com/oauth2callback'
	);
	var $dbconf = array(
		'datasource' => 'Copula.ApisSource',
		'login' => 'login',
		'password' => 'password',
		'authMethod' => 'OAuthV2',
		'scheme' => 'https'
	);
	var $paths = array(
		'host' => 'www.example.com',
		'create' => array(
			'section' => array(
				'path' => array(
					'condition0',
					'condition1',
					'condition2',
					'optional' => array('optional')
				)
			)
		),
		'read' => array('section' => array('path' => array(
					'field0',
					'field1',
					'field2',
					'optional' => array('optional')
			)))
	);
	var $Apis = null;

	public function setUp() {
		parent::setUp();
		Configure::write('Copula.testapi.path', $this->paths);
	//	Configure::write('Copula.testapi.oauth', $this->config);
		$this->Apis = ConnectionManager::create('testapi', $this->dbconf);
		$this->model = ClassRegistry::init('CopulaTestModel');
	}

	function testDescribe() {
		$results = $this->Apis->describe($this->model);
		$this->assertEquals($this->model->schema, $results);
	}

	function testBeforeRequest() {
		$this->model->request = array('request');
		$this->assertEquals(array('request'), $this->Apis->beforeRequest($this->model));
	}

	function testLogQuery() {
		$t = microtime(true);
		$Socket = new HttpSocketOauth();
		$Socket->request['raw'] = 'This is another string';
		$Socket->response = new HttpSocketResponse();
		$Socket->response->raw = "This is a string to be logged";
		Configure::write('debug', '1');
		$this->Apis->took = round((microtime(true) - $t) * 1000, 0);
		$this->Apis->logQuery($Socket);
		$log = $this->Apis->getLog(false);
		$this->assertEquals($Socket->request['raw'], $log['log'][0]['query']);
		$this->assertEquals($Socket->response->raw, $log['log'][0]['response']);
		$this->assertEquals($this->Apis->took, $log['log'][0]['took']);
		$Socket->request['raw'] .= str_repeat('abcdef', 1000);
		$this->Apis->logQuery($Socket);
		$log2 = $this->Apis->getLog(false);
		$this->assertTrue((substr($log2['log'][0]['query'], '-20')) == '[ ... truncated ...]');
	}

	function testDecode() {
		$response = new HttpSocketResponse();
		$expected = array('key' => 'value', 'key' => 'value');
		$response->body = json_encode($expected);
		$response->headers['Content-Type'] = 'application/json; charset=utf-8';
		$this->assertEquals($expected, $this->Apis->decode($response));
		$Xml = Xml::build(file_get_contents(CAKE . 'Test' . DS . 'Fixture' . DS . 'sample.xml'));
		$anotherExpected = Xml::toArray($Xml);
		$anotherResponse = new HttpSocketResponse();
		$anotherResponse->body = file_get_contents(CAKE . 'Test' . DS . 'Fixture' . DS . 'sample.xml');
		$anotherResponse->headers['Content-Type'] = 'application/xml; charset=utf-8';
		$this->assertEquals($anotherExpected, $this->Apis->decode($anotherResponse));
	}

	function testGetHttpObject() {
		$url = 'https://example.com/shop/index.php?product_id=32&highlight=green+dress&cat_id=1&sessionid=123&affid=431';
		$this->Apis->config['authMethod'] = 'OAuth';
		try {
			CakePlugin::path('HttpSocketOauth');
		} catch (MissingPluginException $e) {
			CakePlugin::load('HttpSocketOauth');
		}
		$OauthSocket = $this->Apis->getHttpObject($url);
		$this->assertTrue($OauthSocket instanceof HttpSocketOauth);
		$this->Apis->config['authMethod'] = 'Http';
		$HttpSocket = $this->Apis->getHttpObject($url);
		$this->assertTrue($HttpSocket instanceof HttpSocket);
	}

	function testRead() {
		$query = array(
			'section' => 'section',
			'conditions' => array(
				'field0' => 'value0',
				'field1' => 'value1',
				'field2' => 'value2'
			)
		);
		unset($this->Apis);
		$this->Apis = $this->getMock('ApisSource', array('request'), array($this->dbconf));
		$this->Apis->expects($this->any())
				->method('request')
				->will($this->returnArgument(0));
		$model = $this->Apis->read($this->model, $query);
		$this->assertFalse(empty($model->request));
		$this->assertEquals('GET', $model->request['method']);
		$auth = array('method' => 'OAuth', 'oauth_version' => '2.0', 'client_id' => 'login', 'client_secret' => 'password');
		$this->assertEquals($auth, $model->request['auth']);
	}

	function testReadInvalidSection() {
		$query = array(
			'section' => 'notfound',
			'conditions' => array(
				'field0' => 'value0',
				'field1' => 'value1',
				'field2' => 'value2'
			)
		);
		unset($this->Apis);
		$this->setExpectedException('CakeException');
		$this->Apis = $this->getMock('ApisSource', array('request'), array($this->dbconf));
		$this->Apis->expects($this->any())
				->method('request')
				->will($this->returnArgument(0));
		$this->Apis->read($this->model, $query);
	}

	function testReadWrongConditions() {
		$query = array(
			'section' => 'section',
			'conditions' => array(
				'field1' => 'value1',
				'field2' => 'value2'
			)
		);
		unset($this->Apis);
		$this->setExpectedException('CakeException');
		$this->Apis = $this->getMock('ApisSource', array('request'), array($this->dbconf));
		$this->Apis->expects($this->any())
				->method('request')
				->will($this->returnArgument(0));
		$this->Apis->read($this->model, $query);
	}

	function testCreate() {
		unset($this->Apis);
		$this->Apis = $this->getMock('ApisSource', array('request'), array($this->dbconf));
		$this->Apis->expects($this->any())
				->method('request')
				->will($this->returnArgument(0));
		$fields = array('condition0', 'condition1', 'condition2');
		$values = array('zero', 'one', 'two');
		$model = $this->Apis->create($this->model, $fields, $values);
		$this->assertFalse(empty($model->request['body']));
	}

	function testRequest() {
		unset($this->Apis);
		$this->Apis = $this->getMock('ApisSource', array('getHttpObject'), array($this->dbconf));
		$this->Http = $this->getMock('HttpSocket');
		$response = new HttpSocketResponse();
		$expected = array('key' => 'value', 'key' => 'value');
		$response->body = json_encode($expected);
		$response->headers['Content-Type'] = 'application/json; charset=utf-8';
		$response->code = '200';
		$this->Http->expects($this->once())
				->method('request')
				->will($this->returnValue($response));
		$this->Http->response = $response;
		$this->Apis->expects($this->once())
				->method('getHttpObject')
				->will($this->returnValue($this->Http));
		$this->model->request = array();
		$this->model->request['raw'] = 'This is a test request';
		Configure::write('debug', '1');
		$result = $this->Apis->request($this->model);
		$this->assertFalse(empty($this->Apis->took));
		$this->assertTrue(is_array($result));
		$log = $this->Apis->getLog();
		$this->assertFalse(empty($log['log']));
	}

	public function tearDown() {
		ConnectionManager::drop('testapi');
		unset($this->model, $this->Apis);
		ClassRegistry::flush();
		parent::tearDown();
	}

}

?>