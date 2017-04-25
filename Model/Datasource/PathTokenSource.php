<?php

App::uses('ApisSource', 'Copula.Model/Datasource');

class PathTokenSource extends ApisSource {
		
	/* add an {<key>} to a path and an optional or required
	 *
	 * also can create an array for a path to change paths based on 'one' or 'many' results:
	 * 
	 * eg: 
	 * 'customers' => array(
    		'path' => array(
				'one' => 'api/1.0/customer/{id}',
				'many' => 'api/customers'
			),
        	'optional' => array('id')
        ), 
	 * 
	 */
		
	protected function _buildPath(Model $model, $request_type = 'read', $path = null, $conditions = array(), $authMethod = 'Oauth'){
		$token = null;
		
		
		//need to add in a foreach loop for multiple token replacement:
		
		if(!empty($this->map[$request_type][$model->useTable]['optional'][0])){
			$token = $this->map[$request_type][$model->useTable]['optional'][0];
		}	
		
		if(!empty($this->map[$request_type][$model->useTable]['required'][0])){
			$token = $this->map[$request_type][$model->useTable]['required'][0];
		}
				
		$value = '';
		$selectedPath = is_array($path)? $path['many'] : $path;
			
		if((!empty($conditions[$token]))){
			$selectedPath = is_array($path)? $path['one'] : $path;
			$value = $conditions[$token];
			unset($conditions[$token]);
		}
		if($request_type == 'update'){
			$value = $model->data[$model->name][$token];
		}
		
		$path = str_replace('{'.$token.'}', $value, $selectedPath);
		
		return $path;
	} 
		
}
	
