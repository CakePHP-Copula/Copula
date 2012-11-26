<?php

App::uses('Token', 'Apis.Model');
App::uses('HttpResponse', 'Network/Http');
App::uses('HttpSocket', 'Network/Http');

/**
 * @package cake
 * @subpackage cake.test
 * @property Token $Token
 */
class TokenTestCase extends CakeTestCase {

	var $fixtures = array('plugin.apis.token');
	var $results = array(array(
			'Token' => array(
				'id' => '4',
				'api' => 'Cloudprint',
				'user_id' => '1',
				'access_token' => 'ya29.AHES6ZTopEd2PaRCaLZDd0B9TKNqdt857DYrlC-Welo9d84LaElzAg',
				'refresh_token' => '1/jr6xd0f83uXDh-sBE3eO_lo8qMr11pOQXalzfTAYXGk',
				'modified' => '2012-11-07 23:10:18'
		)));

	function setUp() {
		parent::setUp();
		$this->Token = ClassRegistry::init('Apis.Token');
	}

	function testGetTokenDb() {
		$token = $this->Token->getTokenDb('1', 'Cloudprint');
		$this->assertTrue(!empty($token['access_token']));

		$noToken = $this->Token->getTokenDb('4', 'Cloudprint');
		$this->assertTrue(empty($noToken));
	}

	function testSaveTokenDb() {
		$return = $this->Token->saveTokenDb($this->results[0]['Token'], 'Cloudprint', '12');
		$this->assertTrue(!empty($return['Token']));
	}

	function testBeforeSave() {
		unset($this->Token);
		$this->Token = $this->getMock('Token', array('find'));
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