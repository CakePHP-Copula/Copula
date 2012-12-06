<?php

App::uses('AppController', 'Controller');
App::uses('ApisAppController', 'Apis.Controller');

class AllApiTest extends CakeTestSuite {

	public static function suite() {
		$suite = new CakeTestSuite('All Apis Plugin Tests');
		$suite->addTestDirectoryRecursive(APP . 'Plugin' . DS . 'Apis' . DS . 'Test' . DS . 'Case');
		return $suite;
	}

}

?>