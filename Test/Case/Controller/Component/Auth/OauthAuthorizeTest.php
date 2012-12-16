<?php

App::uses('OauthAuthorize', 'Apis.Controller/Component/Auth');
App::uses('CakeRequest', 'Network');
App::uses('AuthComponent', 'Controller/Component');
App::uses('TokenStoreDb', 'Apis.Model');
App::uses('TokenStoreSession', 'Apis.Model');
App::uses('Controller', 'Controller');

class FakeController extends Controller{
	public $Apis = array('testapi');
}
/**
 * @property OauthAuthorize $auth
 */
class OauthAuthorizeTestCase extends CakeTestCase {

	var $fixtures = array('plugin.apis.token');

	public function setUp() {
		parent::setUp();
		ConnectionManager::create('testapi', array(
			'login' => 'login',
			'password' => 'password',
			'authMethod' => 'OAuthV2',
			'datasource' => 'Apis.ApisSource'
		));
		$this->request = new CakeRequest();
		$this->controller = $this->getMock('FakeController');
		$this->components = $this->getMock('ComponentCollection');
		$this->components->expects($this->any())
				->method('getController')
				->will($this->returnValue($this->controller));
		$this->auth = new OauthAuthorize($this->components);
	}

	public function tearDown() {
		ConnectionManager::drop('testapi');
		unset($this->components, $this->auth, $this->request);
		parent::tearDown();
	}

	public function testConstructor() {
		$this->assertTrue(!empty($this->auth->settings['Apis']));
	}

	public function testAuthorize() {
		$user = array('id' => '1');
		$this->auth->TokenStoreDb = $this->getMock('TokenStoreDb');
		$this->auth->TokenStoreDb->expects($this->any())
				->method('checkToken')
				->will($this->onConsecutiveCalls(false, true));
		$this->assertFalse($this->auth->authorize($user, $this->request));
		$this->assertFalse($this->controller->Apis['testapi']['authorized']);
		$this->assertTrue($this->auth->authorize($user, $this->request));
		$this->assertTrue($this->controller->Apis['testapi']['authorized']);
	}
/*
	public function testAuthorizeNoDb() {
		unset($this->auth->Token);
		$this->auth->Token = $this->getMock('Token');
		$this->auth->Token->expects($this->any())
				->method('getToken')
				->will($this->returnValue(array()));
		$this->assertFalse($this->auth->authorize(array('id' => '1'), $this->request));
	}

	public function testAuthorizeNoSession() {
		$this->auth->settings['Apis']['testapi']['store'] = 'Session';
		$this->assertFalse($this->auth->authorize(array('id' => '1'), $this->request));
	}

	public function testAuthorizeNoCookie() {
		$this->auth->settings['Apis']['testapi']['store'] = 'Cookie';
		$this->assertFalse($this->auth->authorize(array('id' => '1'), $this->request));
	}
*/
}

?>