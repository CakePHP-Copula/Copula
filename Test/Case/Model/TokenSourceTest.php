<?php

App::uses('TokenSource', 'Copula.Model');
App::uses('RemoteTokenSource', 'Copula.Model/Datasource');

class TokenSourceTest extends CakeTestCase {

	public function setUp() {
		parent::setUp();
		$this->datasource = $this->getMock('RemoteTokenSource');
		$this->datasource->expects($this->any())
				->method('read')
				->will($this->returnArgument(1));
		$this->TokenSource = $this->getMock('TokenSource', array('getDataSource', 'setDataSource'));
		$this->TokenSource->expects($this->any())
				->method('getDataSource')
				->will($this->returnValue($this->datasource));
	}

	public function testFindAccess() {
		$query = array('api' => 'testapi', 'requestToken' => array('code' => 'pendency'));
		$expected = array(
			'requestToken' => array('code' => 'pendency'),
			'options' => array(),
			'callbacks' => true
		);
		$result = $this->TokenSource->find('access', $query);
		$this->AssertEquals($expected, $result);
	}

	public function testFindRequest() {
		$query = array('api' => 'testapi');
		$expected = array(
			'requestToken' => array(),
			'options' => array(),
			'callbacks' => true
		);
		$result = $this->TokenSource->find('request', $query);
		$this->AssertEquals($expected, $result);
	}

	public function tearDown() {
		unset($this->datasource, $this->TokenSource);
		parent::tearDown();
	}

}

?>