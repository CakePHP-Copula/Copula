<?php

App::uses('AuthComponent', 'Controller/Component');

class OAuthConsumerBehavior extends ModelBehavior {

	public function setup(\Model $model, $config = array()) {
		if (!isset($this->config[$model->alias])) {
			$this->config[$model->alias] = array(
				'autoFetch' => true
			);
		}
		$this->config[$model->alias] = array_merge(
				$this->config[$model->alias], (array) $config);
		if ($this->config[$model->alias]['autoFetch'] === true) {
			$this->authorize($model, AuthComponent::user('id'));
		}
	}

	/**
	 *
	 * @param \Model $model
	 * @param string $userId
	 * @param TokenStoreInterface $Store
	 * @return boolean
	 */
	function authorize(\Model $model, $userId, TokenStoreInterface $Store = null, $apiName = null) {
		if (empty($Store)) {
			$Store = ClassRegistry::init('Copula.TokenStoreDb');
		}
		if (empty($apiName)) {
			$apiName = $model->useDbConfig;
		}
		$token = $Store->getToken($userId, $apiName);
		if (!empty($token)) {
			ConnectionManager::getDataSource($model->useDbConfig)->setConfig($token);
			return TRUE;
		} else {
			throw new CakeException(__('Could not get access token for Api %s', $model->useDbConfig));
		}
	}

	/**
	 * Utility method to smoothly switch dbconfigs without woes
	 * @param \Model $model
	 * @param string $source
	 * @param string $useTable
	 * @return void
	 * @author Ceeram
	 */
	public function setDbConfig(\Model $model, $source = null, $useTable = null) {
		$datasource = $model->getDataSource();
		if (method_exists($datasource, 'flushMethodCache')) {
			$datasource->flushMethodCache();
		}
		if ($source) {
			$this->config[$model->alias]['default'] = array('useTable' => $this->useTable, 'useDbConfig' => $this->useDbConfig);
			$this->setDataSource($source);
			if ($useTable !== null) {
				$this->setSource($useTable);
			}
		} else {
			if (!empty($this->config[$model->alias]['default'])) {
				$this->setDataSource($this->config[$model->alias]['default']['useDbConfig']);
				$this->setSource($this->config[$model->alias]['default']['useTable']);
				$this->config[$model->alias]['default'] = array();
			}
		}
	}

}

?>