<?php

App::uses('OauthAuthorize', 'Apis.Controller/Component/Auth');
App::uses('CakeRequest', 'Network');
App::uses('AuthComponent', 'Controller/Component');
App::uses('Controller', 'Controller');
App::uses('Token', 'Apis.Model');

class FakeController extends Controller {

	public $name = 'Fake';
	public $components = array('Auth');
	public $uses = array('Fake');

	public function beforeFilter() {
		$this->Auth->authorize = array(
			'OauthAuthorize' => array(
				'Apis' => array('apis')
				));
		parent::beforeFilter();
	}

}

class FakeModel extends CakeTestModel {

	public $name = 'Fake';
	public $useDbConfig = 'apis';

}

/**
 * @property OauthAuthorize $auth
 */
class OauthAuthorizeTestCase extends CakeTestCase {

	public function setUp() {
		parent::setUp();
		$this->controller = new FakeController();
		$this->components = $this->getMock('ComponentCollection');
		$this->components->expects($this->any())
				->method('getController')
				->will($this->returnValue($this->controller));

		$this->auth = new OauthAuthorize(
				$this->components, array('Apis' => array('apis')));
	}

	public function tearDown() {
		unset($this->controller, $this->components, $this->auth);
		parent::tearDown();
	}

	public function testConstructor() {
		$this->assertInstanceOf('Token', $this->auth->Token);
		$this->assertTrue(!empty($this->auth->settings['Apis']));
		$this->assertEquals($this->controller, $this->auth->controller());
	}

	public function testSetAccessToken() {
		$ds = ConnectionManager::create('Test', array('login' => 'user', 'password' => '', 'datasource' => 'Apis.ApisSource'));
		$token = 'test';
		$this->auth->setAccessToken('Test', $token);
		$this->assertEquals('test', $ds->config['access_token']);
	}

	public function testAuthorize() {
		$user = array('id' => '1');
		$request = new CakeRequest();
		//no models are used that require OAuth: do not restrict action
		$this->assertFalse($this->auth->authorize($user, $request));
		$this->controller->Fake = new FakeModel();
		unset($this->auth->Token);
		$this->auth->Token = $this->getMock('Token');
		$this->auth->Token->expects($this->any())
				->method('getTokenDb')
				->will($this->onConsecutiveCalls(array(), array('access_token' => 'token')));
		$this->assertFalse($this->auth->authorize($user, $request));
		$this->auth->settings['Api'] = array('apis');
		xdebug_break();
		$this->assertTrue($this->auth->authorize($user, $request));
	}

	public function testGetDbConfigs() {
		$this->controller->Fake = new FakeModel();
		$results = $this->auth->getDbConfigs($this->controller);
		$this->assertEquals(array('apis'), $results);
	}

}

?>