<?php

App::uses('AppController', 'Controller');
App::uses('CopulaAppController', 'Copula.Controller');

class AllApiTest extends CakeTestSuite {

	public static function suite() {
		$suite = new CakeTestSuite('All Copula Tests');
		$suite->addTestDirectoryRecursive(APP . 'Plugin' . DS . 'Copula' . DS . 'Test' . DS . 'Case');
		return $suite;
	}

}

?>