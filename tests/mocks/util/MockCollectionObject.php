<?php

namespace arthur\tests\mocks\util;

class MockCollectionObject extends \arthur\core\Object 
{
	public $data = array(1 => 2);

	public function testFoo() 
	{
		return 'testFoo';
	}

	public function to($format, array $options = array()) 
	{
		switch($format) 
		{
			case 'array':
				return $this->data + array(2 => 3);
		}
	}
}