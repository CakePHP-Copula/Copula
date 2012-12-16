<?php
App::uses('ApisAppModel', 'Apis.Model');

class TokenSource extends ApisAppModel {

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
			$this->switchDbConfig($query['api'] . 'Token');
			unset($query['api']);
			return $query;
		}
		return $results;
	}

	protected function _findAccess($state, $query, $results = array()) {
		if ($state == 'before') {
			$this->switchDbConfig($query['api'] . 'Token');
			unset($query['api']);
			return $query;
		}
		return $results;
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