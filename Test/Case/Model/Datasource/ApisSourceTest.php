<?php

App::uses('ApisSource', 'Copula.Model/Datasource');
App::uses('AppModel', 'Model');
App::uses('PhpReader', 'Configure');
App::uses('HttpSocketResponse', 'Network/Http');
App::uses('HttpSocket', 'Network/Http');

class CopulaTestModel extends AppModel {

	public $useDbConfig = "testapi";
	public $useTable = 'section';
	public $schema = array(
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

	public $request = array(
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
	public $config = array(
		'version' => '2.0',
		'scheme' => 'https',
		'host' => 'www.example.com',
		'authMethod' => 'OAuthV2',
		'scheme' => 'https'
	);
	public $dbconf = array(
		'login' => 'login',
		'password' => 'password',
		'datasource' => 'Copula.ApisSource'
	);
	public $paths = array(
		'create' => array(
			'section' => array(
				'path' => 'somepath',
				'required' => array(
					'condition0',
					'condition1',
					'condition2'),
				'optional' => array('optional')
			)
		),
		'read' => array(
			'section' => array(
				'path' => 'path_to_enlightenment',
				'required' => array(
					'field0',
					'field1',
					'field2'),
				'optional' => array('optional')
		))
	);
	public $Apis = null;

	public function setUp() {
		parent::setUp();
		Configure::write('Copula.testapi.path', $this->paths);
		Configure::write('Copula.testapi.Api', $this->config);
		$this->Apis = ConnectionManager::create('testapi', $this->dbconf);
		$this->model = ClassRegistry::init('CopulaTestModel');
	}

	public function testDescribe() {
		$results = $this->Apis->describe($this->model);
		$this->assertEquals($this->model->schema, $results);
	}

	public function testDescribeMap() {
		$this->Apis->map['testapi'] = array('create' => array('stuff'));
		unset($this->model->schema);
		$results = $this->Apis->describe($this->model);
		$this->assertEquals($this->Apis->map['testapi'], $results);
	}

	public function testDescribeNull() {
		unset($this->model->schema);
		$this->assertNull($this->Apis->describe($this->model));
	}

	public function testListSources() {
		$this->Apis->map['create'] = array('endpoint' => array('path' => 'arbitrary'));
		$results = $this->Apis->listSources();
		$this->assertEquals(array('endpoint'), $results);
		unset($this->Apis->map['create']);
		$this->assertNull($this->Apis->listSources());
	}

	public function testBeforeRequest() {
		$this->model->request = array('request');
		$this->assertEquals(array('request'), $this->Apis->beforeRequest($this->model));
	}

	public function testLogQuery() {
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

	public function testDecode() {
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
		$moarResponse = new HttpSocketResponse();
		$response->body = '<!doctype html><html lang=en><head><meta charset=utf-8><title>blah</title></head><body><p>pizza!</p></body></html>';
		$moarResponse->headers['Content-Type'] = 'text/html; charset=utf-8';
		$this->assertEquals($moarResponse->body, $this->Apis->decode($moarResponse));
	}

	public function testGetHttpObject() {
		$url = 'https://example.com/shop/index.php?product_id=32&highlight=green+dress&cat_id=1&sessionid=123&affid=431';
		$OauthSocket = $this->Apis->getHttpObject('OAuthV2', $url);
		$this->assertTrue($OauthSocket instanceof HttpSocketOauth);
		$HttpSocket = $this->Apis->getHttpObject('Http', $url);
		$this->assertTrue($HttpSocket instanceof HttpSocket);
	}

	public function testRead() {
		$query = array(
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
		$queried = 'field0=value0&field1=value1&field2=value2';
		$this->assertEquals($queried, $model->request['uri']['query']);
	}

	public function testReadInvalidSection() {
		$query = array(
			'conditions' => array(
				'field0' => 'value0',
				'field1' => 'value1',
				'field2' => 'value2'
			)
		);
		$this->model->useTable = 'notfound';
		unset($this->Apis);
		$this->setExpectedException('CakeException');
		$this->Apis = $this->getMock('ApisSource', array('request'), array($this->dbconf));
		$this->Apis->expects($this->any())
				->method('request')
				->will($this->returnArgument(0));
		$this->Apis->read($this->model, $query);
	}

	public function testReadWrongConditions() {
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

	public function testCreate() {
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

	public function testRequest() {
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

	public function testSwapTokens() {
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
		$this->model->request['uri']['path'] = '/this/:is_the/end/:';
		$this->Apis->tokens = array('is_the' => '', '' => 'up');
		$result = $this->Apis->request($this->model);
		$this->assertEquals('/this//end/up', $this->model->request['uri']['path']);
	}

	public function testColumnType() {
		$this->assertTrue($this->Apis->getColumnType());
	}

	public function testCalculate() {
		$this->assertEquals('COUNT', $this->Apis->calculate($this->model, 'string'));
	}

	public function testCount() {
		$expected = array(array(array('count' => 1)));
		$results = $this->Apis->read($this->model, array('fields' => 'COUNT'));
		$this->assertEquals($expected, $results);
	}

	public function testAfterRequestFailed() {
		$response = new HttpSocketResponse();
		$response->code = 403;
		$this->assertFalse($this->Apis->afterRequest($this->model, $response));
	}

	public function testDelete() {
		$this->Apis = $this->getMock('ApisSource', array('request'), array($this->dbconf));
		$this->Apis->expects($this->any())
				->method('request')
				->will($this->returnArgument(0));
		$conditions = array('field0' => 'value');
		$this->Apis->map['delete'] = $this->paths['read'];
		$this->Apis->map['delete']['section']['required'] = array();
		$model = $this->Apis->delete($this->model, $conditions);
		$this->assertEquals('field0=value', $model->request['body']);
	}

	public function testUpdate() {
		$this->Apis = $this->getMock('ApisSource', array('request'), array($this->dbconf));
		$this->Apis->expects($this->any())
				->method('request')
				->will($this->returnArgument(0));
		$conditions = array('field0' => 'value');
		$fields = array('condition0', 'condition1', 'condition2');
		$values = array('zero', 'one', 'two');
		$this->Apis->map['update'] = $this->paths['read'];
		$this->Apis->map['update']['section']['required'] = array();
		$model = $this->Apis->update($this->model, $fields, $values, $conditions);
		$this->assertEquals('condition0=zero&condition1=one&condition2=two', $model->request['body']);
	}

	public function tearDown() {
		ConnectionManager::drop('testapi');
		unset($this->model, $this->Apis);
		ClassRegistry::flush();
		parent::tearDown();
	}

}

?>