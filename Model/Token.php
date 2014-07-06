<?php

App::uses('AppModel', 'Model');

class Token extends AppModel {

	protected $_credentials = array();

	/**
	 * This fetches the access token from the remote API
	 *
	 * @return array|string Probably returns an array, but it could be a string too
	 */
	public function fetch() {
			$this->getDataSource()->setConfig($this->credentials());
			$data = $this->find('first');
			$this->set($data);
			return $data;
	}

	/**
	 * Stub implementation of token expiration. This is written with OAuthV2 in mind.
	 *
	 * @return boolean Whether the token is expired, based on assumptions about how it's stored
	 */
	public function isExpired() {
		if (!empty($this->data)) {
			$expires = $this->data['expires_in'];
			$now = strtotime('now');
			$modified = strtotime($this->data['modified']);
			$interval = $now - $modified;
			return ($interval > $expires) ? true : false;
		}
		return true;
	}

	/**
	 * A stub implementation of refresh behavior. This is written with OAuthV2 in mind.
	 *
	 * @return array|string The token data
	 */
	public function refresh() {
		$this->create();
		return $this->fetch();
	}

	/**
	 * Returns or sets the credentials for this token.
	 *
	 * @param array $creds Any credentials used with this token
	 * @return array The credentials, or an empty array
	 */
	public function credentials($creds = array()) {
		if (!empty($creds)) {
			$this->_credentials = $creds;
		}
		return $this->_credentials;
	}
}
