<?php

App::import('Controller', 'Cloudprint.Oauth');

class TestOauthController extends OauthController {

	var $name = "Oauth";
	var $autoRender = false;

	function redirect($url, $status = null, $exit = true) {
		$this->redirectUrl = $url;
	}

	function render($action = null, $layout = null, $file = null) {
		$this->renderedAction = $action;
	}

	function _stop($status = 0) {
		$this->stopped = $status;
	}

}

/**
 * @package cake
 * @subpackage cake.cake.test.libs
 * @property TestOauthController $Oauth
 */
class OauthControllerTestCase extends CakeTestCase {

	function startTest() {
		$this->Oauth = new TestOauthController();
		$this->Oauth->constructClasses();
		$this->Oauth->Component->initialize($this->Oauth);
	}

	function testAuthorize() {
		$expected = "https://accounts.google.com/o/oauth2/auth?scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fcloudprint&redirect_uri=https%3A%2F%2Fsplat.dnsd.me%2Foauth2callback&response_type=code&client_id=286950796224.apps.googleusercontent.com&access_type=offline&approval_prompt=auto&state=%2F";
		$this->Oauth->authorize();
		$this->assertEqual($expected, $this->Oauth->redirectUrl);
	}

	function testRedirect() {
		$expected = "https://accounts.google.com/o/oauth2/auth?scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fcloudprint&redirect_uri=https%3A%2F%2Fsplat.dnsd.me%2Foauth2callback&response_type=code&client_id=286950796224.apps.googleusercontent.com&access_type=offline&approval_prompt=auto&state=%2Fcloudprint%2Fprinters%2Findex";
		$this->Oauth->authorize("auto", '/cloudprint/printers/index');
		$this->assertEqual($expected, $this->Oauth->redirectUrl);
	}

	function testCallback() {
		App::import('Component', 'SessionComponent');
		App::import('Model', 'Cloudprint.Token');
		Mock::generate('SessionComponent');
		Mock::generate('Token');
		$this->Oauth->Session = new MockSessionComponent;
		$this->Oauth->Token = new MockToken();
		$this->Oauth->Token->setReturnValue('getAccessToken', 'returnvalue', array("testcode", "authorization_code"));
		$this->Oauth->Token->expectCallCount('getAccessToken', 3);
		$this->Oauth->params['url']['code'] = "testcode";
		$this->Oauth->params['url']['state'] = "/";
		$this->Oauth->Session->setReturnValue('read', 1, array('Auth.Vendor.id'));
		$this->Oauth->Token->expectCallCount('saveTokenDb', 2);
		$expectedRedirect = Router::url('/');
		$this->Oauth->callback();
		$this->assertEqual($expectedRedirect, $this->Oauth->redirectUrl);
		//
		$this->Oauth->params['url']['state'] = 'force';
		$this->Oauth->callback();
		$this->assertEqual($expectedRedirect, $this->Oauth->redirectUrl);
		//
		$this->Oauth->params['url']['state'] = "/cloudprint/printers/index";
		$otherRedirect = Router::url('/cloudprint/printers/index');
		$this->Oauth->callback();
		$this->assertEqual($otherRedirect, $this->Oauth->redirectUrl);
		//
		unset($this->Oauth->params['url']['code']);
		$this->Oauth->Session->expectOnce('setFlash');
		$this->Oauth->callback();
		$this->assertEqual($expectedRedirect, $this->Oauth->redirectUrl);
	}

	function endTest() {
		unset($this->Oauth);
		ClassRegistry::flush();
	}

}

?>