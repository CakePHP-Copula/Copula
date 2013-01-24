<?php

App::uses('CopulaAppModel', 'Copula.Model');

class TokenSource extends CopulaAppModel {

	public $findMethods = array('request' => true, 'access' => true);

	public $useTable = false;

	public function buildQuery($type = 'access', $query = array()) {
		$query = array_merge(array(
			'options' => array(),
			'api' => null,
			'requestToken' => array(),
			'callbacks' => true
				), $query);

		if ($this->findMethods[$type] === true) {
			$query = $this->{'_find' . ucfirst($type)}('before', $query);
		}

		if ($query['callbacks'] === true || $query['callbacks'] === 'before') {
			$event = new CakeEvent('Model.beforeFind', $this, array($query));
			list($event->break, $event->breakOn, $event->modParams) = array(true, array(false, null), 0);
			$this->getEventManager()->dispatch($event);
			if ($event->isStopped()) {
				return null;
			}
			$query = $event->result === true ? $event->data[0] : $event->result;
		}
		return $query;
	}

	protected function _findRequest($state, $query, $results = array()) {
		if ($state == 'before') {
			return $this->beforeQuery($query);
		}
		return $results;
	}

	protected function _findAccess($state, $query, $results = array()) {
		if ($state == 'before') {
			return $this->beforeQuery($query);
		}
		return $results;
	}

	public function beforeQuery($query) {
		$api = $query['api'];
		if (isset(ConnectionManager::$config->{$api}) && !isset(ConnectionManager::$config->{$api . 'Token'})) {
			$static = Configure::read("Copula.$api.Auth");
			$datasourceConfig =ConnectionManager::getDataSource($api)->config;
			$config = array_merge($datasourceConfig, $static);
			$config['datasource'] = 'Copula.RemoteTokenSource';
			ConnectionManager::create($api . 'Token', $config);
		}
		$this->switchDbConfig($api . 'Token');
		unset($query['api']);
		return $query;
	}

	public function switchDbConfig($new) {
		$datasource = $this->getDataSource();
		if (method_exists($datasource, 'flushMethodCache')) {
			$datasource->flushMethodCache();
		}
		$this->setDataSource($new);
	}

}

?>