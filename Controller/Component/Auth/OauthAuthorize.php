<?php

App::uses('BaseAuthorize', 'Controller/Component/Auth');
App::uses('Token', 'Apis.Model');

class OauthAuthorize extends BaseAuthorize {

	function __construct(\ComponentCollection $collection, $settings = array()) {
		$this->Token = ClassRegistry::init('Token');
		$defaults = array('Apis' => array());
		$settings = array_merge($settings, $defaults);
		parent::__construct($collection, $settings);
	}

	function authorize($user, \CakeRequest $request) {
		$controller = $this->controller();
		if ($controller->uses === true) {
			$className = $controller->modelClass;
			$dbs[] = $controller->{$className}->useDbConfig;
		} elseif (is_array($controller->uses)) {
			foreach ($controller->uses as $modelName) {
				list($plugin, $class) = pluginSplit($modelName);
				$dbs[] = $controller->{$class}->useDbConfig;
			}
		} else {
			// no attached models
			return false;
		}
		$apiNames = array_intersect($this->settings['Apis'], $dbs);
		if ($this->settings['store'] == 'Session') {
			// @todo implement session retrieval
		} else {
			foreach ($apiNames as $index => $apiName) {
				$token = $this->Token->getTokenDb($user['id'], $apiName);
				if (!empty($token['access_token'])) {
					$this->setAccessToken(strtolower($apiName), $token['access_token']);
				} else {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * 
	 * @param type $datasource
	 * @param type $token token to use for this db.
	 * @return void
	 */
	function setAccessToken($datasource, $token) {
		$ds = ConnectionManager::getDataSource($datasource);
		$ds->config['access_token'] = $token;
	}

}

?>