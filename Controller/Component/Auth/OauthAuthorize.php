<?php
App::uses('BaseAuthorize', 'Controller/Component/Auth');
App::uses('Token', 'Apis.Model');

/**
 * @property Token $Token
 */
class OauthAuthorize extends BaseAuthorize {

	public function __construct(\ComponentCollection $collection, $settings = array()) {
		$this->Token = ClassRegistry::init('Apis.Token');
		$defaults = array('Apis' => array(), 'store' => '');
		$settings = array_merge($defaults, $settings);
		parent::__construct($collection, $settings);
	}

	public function authorize($user, \CakeRequest $request) {
		$controller = $this->controller();
		$dbs = $this->getDbConfigs($controller);
		if(empty($dbs)){
			// no attached models
			return false;
		}
		$apiNames = array_intersect($this->settings['Apis'], $dbs);
		if ($this->settings['store'] == 'Session') {
			// @todo implement session retrieval
			return false;
		} else {
			$count = 0;
			foreach ($apiNames as $index => $apiName) {
				$token = $this->Token->getTokenDb($user['id'], $apiName);
				if (!empty($token['access_token'])) {
					$this->setAccessToken(strtolower($apiName), $token['access_token']);
					$count++;
				} else {
					return false;
				}
			}
			if($count === count($this->settings['Apis'])){
				return true;
			}
		}
		return false;
	}

	/**
	 * 
	 * @param type $datasource
	 * @param type $token token to use for this db.
	 * @return void
	 */
	public function setAccessToken($datasource, $token) {
		$ds = ConnectionManager::getDataSource($datasource);
		$ds->config['access_token'] = $token;
	}

	/**
	 * 
	 * @param \Controller $controller
	 */
	public function getDbConfigs(\Controller $controller) {
		$dbs = array();
		if ($controller->uses === true) {
			$className = $controller->modelClass;
			$dbs[] = $controller->{$className}->useDbConfig;
		} elseif (is_array($controller->uses)) {
			foreach ($controller->uses as $modelName) {
				list($plugin, $class) = pluginSplit($modelName);
				$dbs[] = $controller->{$class}->useDbConfig;
			}
		}
		return $dbs;
	}

}

?>