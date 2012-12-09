<?php

App::uses('OauthAuthorize', 'Apis.Controller/Component/Auth');
App::uses('CakeRequest', 'Network');
App::uses('AuthComponent', 'Controller/Component');
App::uses('Token', 'Apis.Model');
App::uses('Controller', 'Controller');

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
		$this->controller = $this->getMock('Controller');
		$this->components = $this->getMock('ComponentCollection');
		$this->components->expects($this->any())
				->method('getController')
				->will($this->returnValue($this->controller));
		$this->auth = new OauthAuthorize(
				$this->components, array('Apis' => array('testapi' => array('store' => ''))));
	}

	public function tearDown() {
		ConnectionManager::drop('testapi');
		unset($this->components, $this->auth, $this->request);
		parent::tearDown();
	}

	public function testConstructor() {
		$this->assertInstanceOf('Token', $this->auth->Token);
		$this->assertTrue(!empty($this->auth->settings['Apis']));
	}

	public function testAuthorizeTrue() {
		$user = array('id' => '1');
		$this->assertTrue($this->auth->authorize($user, $this->request));
	}

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

}

?>