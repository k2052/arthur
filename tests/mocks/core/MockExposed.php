<?php

namespace arthur\tests\mocks\core;

class MockExposed extends \arthur\core\Object 
{
	protected $_internal = 'secret';

	public function tamper() 
	{
		$internal =& $this->_internal;

		return $this->_filter(__METHOD__, array(), function() use (&$internal) 
		{
			$internal = 'tampered';
			return true;
		});
	}

	public function get() 
	{
		return $this->_internal;
	}
}