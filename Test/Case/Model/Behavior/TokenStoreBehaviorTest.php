<?php

App::uses('TokenStoreBehavior', 'Copula.Model/Behavior');
App::uses('TokenSource', 'Copula.Model');
App::uses('Model', 'Model');

/**
 * Tests functionality of AccessToken behavior
 */
class AccessTokenBehaviorTestCase extends CakeTestCase {

	function setUp() {
		parent::setUp();
		$this->Token = new TokenStoreBehavior();
		$this->Model = $this->getMock('Model');
	}

	function tearDown() {
		unset($this->Token);
		parent::tearDown();
	}

	function testIsExpired() {
		$newer = array(
			'modified' => date('Y-m-d H:i:s', strtotime('-5 min')),
			'expires_in' => '3600'
		);
		$older = array(
			'modified' => '2012-11-07 23:10:18',
			'expires_in' => '3600'
		);
		$this->assertTrue($this->Token->isExpired($this->Model, $older));
		$this->assertFalse($this->Token->isExpired($this->Model, $newer));
	}

	public function testSetup() {
		$this->Token->setup($this->Model);
		$this->assertTrue($this->Token->TokenSource instanceof TokenSource);
	}
//@todo finish tests
}

?>