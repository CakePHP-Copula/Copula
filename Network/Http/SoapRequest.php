<?php

class SoapRequest extends Request {

	public function method($method = null) {
		if ($method === null) {
			return $this->_method;
		}
		$this->_method = $method;
		return $this;
	}

}
