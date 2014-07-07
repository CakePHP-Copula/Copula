<?php

App::uses('Request', 'Copula.Network/Http');

class RequestTest extends CakeTestCase {

	public function setUp() {
		parent::setUp();
		$this->Request = new Request();
	}

	public function tearDown() {
		unset($this->Request);
		parent::tearDown();
	}

	public function testIsValid() {
		$url['query'] = array(
			'name' => 'JoeBloggs',
			'date' => date('Y-m-d')
		);
		$rules = array(
			'name' => 'alphaNumeric',
			'date' => 'date'
		);
		$this->Request->method('GET');
		$this->Request->url($url);
		$result = $this->Request->isValid($rules);
		$this->assertTrue($result);
	}

	public function testIsValidPost() {
		$data = array(
			'name' => 'BobDobbs',
			'date' => date('Y-m-d')
		);
		$rules = array(
			'name' => 'alphaNumeric',
			'date' => 'date'
		);
		$this->Request->method('POST');
		$this->Request->body($data);
		$result = $this->Request->isValid($rules);
		$this->assertTrue($result);
	}
}