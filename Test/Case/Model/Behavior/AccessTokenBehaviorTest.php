<?php

App::uses('Token', 'Apis.Model');
App::uses('AccessTokenBehavior', 'Apis.Model/Behavior');
App::uses('HttpSocket', 'Network/Http');
App::uses('HttpResponse', 'Network/Http');

class AccesstokenTestModel extends CakeTestModel {

	public $name = "Accesstoken";
	public $useTable = false;
	public $useDbConfig = "Cloudprint";
	public $actsAs = array('Apis.AccessToken');

}

/**
 * Tests functionality of AccessToken behavior
 * @property Accesstoken $Accesstoken
 */
class AccessTokenBehaviorTestCase extends CakeTestCase {

	var $fixtures = array('plugin.apis.token');

	function setUp() {
		parent::setUp();
		CakePlugin::load('Cloudprint');
		$this->Accesstoken = new AccesstokenTestModel();
	}

	function tearDown() {
		unset($this->Accesstoken);
		parent::tearDown();
	}

	function testSetup() {
		$this->assertTrue(isset($this->Accesstoken->Behaviors->AccessToken->config['Accesstoken']));
		$this->assertTrue(is_object($this->Accesstoken->Socket));
	}

	function testGetCredentials() {
		$results = $this->Accesstoken->getCredentials();
		$this->assertTrue(!empty($results['key']) && !empty($results['secret']));
	}

	function testGetRemoteToken() {
		unset($this->Accesstoken->Socket);
		$response = new HttpResponse();
		$response->body = json_encode(array(
			'access_token' => 'ya29.AHES6ZTopEd2PaRCaLZDd0B9TKNqdt857DYrlC-Welo9d84LaElzAg',
			'refresh_token' => '1/jr6xd0f83uXDh-sBE3eO_lo8qMr11pOQXalzfTAYXGk'
				));
		$response->code = '200';
		$this->getMock('HttpSocket', array('request'), array(), 'MockHttpSocket');
		$this->Accesstoken->Socket = new MockHttpSocket();
		$this->Accesstoken->Socket->expects($this->once())
				->method('request')
				->will($this->returnValue($response));
		$result = $this->Accesstoken->getRemoteToken('code', 'authorization_code');
		$expected = array(
			'access_token' => 'ya29.AHES6ZTopEd2PaRCaLZDd0B9TKNqdt857DYrlC-Welo9d84LaElzAg',
			'refresh_token' => '1/jr6xd0f83uXDh-sBE3eO_lo8qMr11pOQXalzfTAYXGk'
		);
		$this->assertEquals($expected, $result);
	}

	function testIsExpired() {
		$newer['modified'] = date('Y-m-d H:i:s', strtotime('-5 min'));
		$older['modified'] = '2012-11-07 23:10:18';
		$this->assertTrue($this->Accesstoken->isExpired($older));
		$this->assertFalse($this->Accesstoken->isExpired($newer));
	}

	function testGetRefreshAccess() {
		$this->getMock('AccessTokenBehavior', array('getRemoteToken'), array(), 'MockAccess');
		$this->Access = new MockAccess();
		$token = array(
			'access_token' => 'test1',
			'refresh_token' => 'replace'
		);
		$this->Access->expects($this->once())
				->method('getRemoteToken')
				->will($this->returnValue($token));
		$this->getMock('AccesstokenTestModel', array('field', 'saveField'), array(), 'MockModel');
		$this->Model = new MockModel();
		$this->Model->expects($this->once())->method('saveField');
		$this->Model->expects($this->once())
				->method('field')
				->will($this->returnValue('test3'));
		$this->Access->setup($this->Model);
		$test = array('access_token' => 'replace', 'refresh_token' => 'replace', 'user_id' => '42', 'id' => '4');
		$results = $this->Access->getRefreshAccess($this->Model, $test);
		$this->assertEquals('test1', $results['access_token']);
		$this->assertEquals('replace', $results['refresh_token']);
		$this->assertTrue(!empty($results['modified']));
		$this->assertTrue(!empty($results['id']));
		$this->assertEquals('Cloudprint', $results['api']);
	}

	function testGetToken() {
		$Token = ClassRegistry::init('Apis.Token');
		$token = $Token->getTokenDb('1', 'Cloudprint');
		$this->Access = $this->getMock('AccessTokenBehavior', array('isExpired', 'getRefreshAccess'));
		$this->Access->setup($this->Accesstoken);
		$this->Access->expects($this->any())
				->method('isExpired')
				->will($this->onConsecutiveCalls(false, true));
		$expected = array(
			'access_token' => 'ya29.AHES6ZTopEd2PaRCaLZDd0B9TKNqdt857DYrlC-Welo9d84LaElzAg',
			'refresh_token' => '1/jr6xd0f83uXDh-sBE3eO_lo8qMr11pOQXalzfTAYXGk'
		);
		$this->Access->expects($this->once())
				->method('getRefreshAccess')
				->will($this->returnValue($expected));
		$results = $this->Access->getToken($this->Accesstoken, $token['user_id']);
		$this->assertEquals($expected['access_token'], $results['access_token']);
		$this->assertEquals($expected['refresh_token'], $results['refresh_token']);
		$moar = $this->Access->getToken($this->Accesstoken, $token['user_id']);
	}

}

?>