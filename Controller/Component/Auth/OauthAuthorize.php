<?php

App::uses('BaseAuthorize', 'Controller/Component/Auth');
App::uses('Token', 'Copula.Model');

/**
 * @property Token $Token
 */
class OauthAuthorize extends BaseAuthorize {

	public function __construct(\ComponentCollection $collection, $settings = array()) {
		$this->settings['Apis'] = $collection->getController()->Apis;
		parent::__construct($collection, $settings);
	}

	public function authorize($user, \CakeRequest $request) {
		$dbs = ConnectionManager::enumConnectionObjects();
		$connNames = array_keys($dbs);
		$Apis = (key($this->settings['Apis']) === 0) ? $this->settings['Apis'] : array_keys($this->settings['Apis']);
		$apiNames = array_intersect($Apis, $connNames);
		$authorized = true;
		foreach ($apiNames as $name ) {
			$storeMethod = (empty($this->settings['Apis'][$name]['store'])) ? 'Db' : ucfirst($this->settings['Apis'][$name]['store']);
			$Store = $this->_getTokenStore($storeMethod);
			if ($Store instanceof TokenStoreInterface) {
				$allowed = $Store->checkToken($name, $user['id']);
			}
			$this->controller()->Apis[$name]['authorized'] = $allowed;
			if (!$allowed) {
				$authorized = false;
			}
		}
		return $authorized;
	}

	protected function _getTokenStore($storeMethod) {
		if (!isset($this->{'TokenStore' . $storeMethod})) {
			$this->{'TokenStore' . $storeMethod} = ClassRegistry::init('Copula.TokenStore' . $storeMethod);
		}
		return $this->{'TokenStore' . $storeMethod};
	}

}

?>