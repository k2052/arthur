<?php

namespace arthur\tests\mocks\util;

class MockCollectionMarker 
{
	public $marker = false;
	public $data = 'foo';

	public function mark() 
	{
		$this->marker = true;
		return true;
	}

	public function mapArray() 
	{
		return array('foo');
	}
}