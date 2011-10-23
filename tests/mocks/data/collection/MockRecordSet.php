<?php

namespace arthur\tests\mocks\data\collection;

class MockRecordSet extends \arthur\data\collection\RecordSet 
{
	public function get($var) 
	{
		return $this->{$var};
	}

	public function set($var, $value) 
	{
		$this->{$var} = $value;
	}
}