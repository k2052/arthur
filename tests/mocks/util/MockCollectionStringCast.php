<?php

namespace arthur\tests\mocks\util;

class MockCollectionStringCast 
{
	protected $data = array(1 => 2, 2 => 3);

	public function __toString() 
	{
		return json_encode($this->data);
	}
}