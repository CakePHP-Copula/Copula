<?php

App::import('Core', 'HttpSocket');

/**
 *
 * @subpackage controller
 * @package Cloudprint
 * @property Token $Token
 * @property SessionComponent $Session
 * @property AuthComponent $Auth
 */
class OauthController extends CloudprintAppController {

	public $name = "Oauth";
	public $uses = "Cloudprint.Token";
	public $Http;

	function beforeFilter() {
		$this->Auth->allow('*');
		parent::beforeFilter();
	}

	function __construct() {
		$this->Http = new HttpSocket();
		parent::__construct();
	}

	/**
	 * The first part of the OAuth Dance
	 *
	 * Redirects user to Google's Authorization page. If user does not have a Google Account, they're not going to get very far.
	 * @param string $approval "force" or "auto", "force" can be used to get a one-time token
	 * @param string $redirect  set to "force" if using a one-time token, otherwise it should be a string that can be parsed by $controller->redirect();
	 */
	function authorize($approval = "auto", $redirect = "/") {
		if ($this->Session->check('Auth.redirect')) {
			$redirect = $this->Session->read('Auth.redirect');
		}
		Configure::load('cloudprint');
		$config = Configure::read('Apis.Cloudprint');
		$request = array(
			'scheme' => $config['oauth']['scheme'],
			'host' => $config['hosts']['oauth'],
			'path' => $config['oauth']['authorize'],
			'query' => array(
				'scope' => $config['scope'],
				'redirect_uri' => $config['callback'],
				'response_type' => 'code',
				'client_id' => $config['oauth']['key'],
				'access_type' => 'offline',
				'approval_prompt' => $approval,
				'state' => $redirect,
			),
		);

		$this->redirect($this->Http->_buildUri($request));
	}

	/**
	 * The second and final parts of the OAuth dance
	 *
	 * This function takes parameters from the URL, not the standard cake arguments. Probably clever routing could fix this.
	 * It exchanges the authorization code for an access_token and refresh_token, and saves these to the database. It then redirects the user based on the second parameter.
	 * It would actually be nice to have a third parameter here but it would have to be packed into a url string in the authorize() function.
	 */
	public function callback() {
		if (!empty($this->params['url']['code'])) {
			$oAuthCode = $this->params['url']['code'];
			$grant_type = "authorization_code";
			$access_token = $this->Token->getAccessToken($oAuthCode, $grant_type);
			if ($access_token && $this->params['url']['state'] != 'force') {
				$this->Token->saveTokenDb($this->Session->read('Auth.Vendor.id'), $access_token);
			}
			$this->Session->write("OAuth.Cloudprint.access_token", $access_token);
			$this->redirect(($this->params['url']['state'] == 'force') ? "/" : $this->params['url']['state']);
		} else {
			$this->Session->setFlash('You chose not to allow access.');
			$this->redirect('/');
		}
	}

}

?>