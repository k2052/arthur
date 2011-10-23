<?php

namespace arthur\tests\mocks\storage\cache\strategy;

class MockSerializer extends \arthur\core\Object 
{
	public function write($data) 
	{
		return serialize($data);
	}

	public function read($data) 
	{
		return unserialize($data);
	}
}