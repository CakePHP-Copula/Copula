<?php

App::uses('TokenStoreDb', 'Copula.Model');
App::uses('HttpResponse', 'Network/Http');
App::uses('HttpSocket', 'Network/Http');

/**
 * @package cake
 * @subpackage cake.test
 * @property Token $Token
 */
class TokenTestCase extends CakeTestCase {

	var $fixtures = array('plugin.copula.tokenstoredb');
	var $results = array(array(
			'TokenStoreDb' => array(
				'id' => '4',
				'api' => 'testapi',
				'user_id' => '1',
				'access_token' => 'ya29.AHES6ZTopEd2PaRCaLZDd0B9TKNqdt857DYrlC-Welo9d84LaElzAg',
				'refresh_token' => '1/jr6xd0f83uXDh-sBE3eO_lo8qMr11pOQXalzfTAYXGk',
				'expires_in' => '3600',
				'modified' => '2012-11-07 23:10:18'
		)));

	function setUp() {
		parent::setUp();
		$this->Token = ClassRegistry::init('Copula.TokenStoreDb');
	}

	function testGetTokenDb() {
		$token = $this->Token->getToken('6', 'cloudprint');
		$this->assertTrue(!empty($token['access_token']));

		$noToken = $this->Token->getToken('99', 'testapi');
		$this->assertTrue(empty($noToken));
	}

	function testSaveTokenDb() {
		$return = $this->Token->saveToken($this->results[0][$this->Token->alias], 'testapi', '12', '2.0');
		$this->assertTrue(!empty($return[$this->Token->alias]));
	}

	function testBeforeSave() {
		unset($this->Token);
		$this->Token = $this->getMock('TokenStoreDb', array('find'));
		$this->Token->expects($this->any())
				->method('find')
				->will($this->returnValue($this->results));
		$this->Token->data = $this->results['0'];
		$this->assertTrue($this->Token->beforeSave());
		$this->assertEquals('4', $this->Token->id);
	}

	function tearDown() {
		unset($this->Token);
		ClassRegistry::flush();
		parent::tearDown();
	}

}

?>