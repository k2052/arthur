<?php

namespace arthur\tests\mocks\core;

class MockRequest extends \arthur\core\Object 
{
	public $url = null;
	public $params = array();
	public $argv = array();

	public function env($key) 
	{
		if(isset($this->_config[$key]))
			return $this->_config[$key];

		return null;
	}
}