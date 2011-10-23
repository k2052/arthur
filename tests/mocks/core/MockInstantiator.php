<?php

namespace arthur\tests\mocks\core;

class MockInstantiator extends \arthur\core\Object 
{
	protected $_classes = array('request' => '\arthur\tests\mocks\core\MockRequest');

	public function instance($name, array $config = array()) 
	{
		return $this->_instance($name, $config);
	}
}