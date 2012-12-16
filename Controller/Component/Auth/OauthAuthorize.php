<?php

App::uses('BaseAuthorize', 'Controller/Component/Auth');
App::uses('Token', 'Apis.Model');
App::uses('OauthConfig', 'Apis.Lib');

/**
 * @property Token $Token
 */
class OauthAuthorize extends BaseAuthorize {

	public function __construct(\ComponentCollection $collection, $settings = array()) {
		$this->settings['Apis'] =& $collection->getController()->Apis;
		parent::__construct($collection, $settings);
	}

	public function authorize($user, \CakeRequest $request) {
		$dbs = ConnectionManager::sourceList();
		$apiNames = array_intersect($this->settings['Apis'], $dbs);
		$authorized = true;
		foreach ($apiNames as $name) {
			$storeMethod = (empty($this->settings['Apis'][$name]['store'])) ? 'Db' : ucfirst($this->settings['Apis'][$name]['store']);
			$Store = $this->_getTokenStore($storeMethod);
			if ($Store instanceof TokenStoreInterface) {
				$allowed = $Store->checkToken($name, $user['id']);
			}
			$this->settings['Apis'][$name]['authorized'] = $allowed;
			if (!$allowed) {
				$authorized = false;
			}
		}
		return $authorized;
	}

	protected function _getTokenStore($storeMethod) {
		if (!isset($this->{'TokenStore' . $storeMethod})) {
			$this->{'TokenStore' . $storeMethod} = ClassRegistry::init('Apis.TokenStore' . $storeMethod);
		}
		return $this->{'TokenStore'. $storeMethod};
	}

}

?>