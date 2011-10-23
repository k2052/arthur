<?php

namespace arthur\tests\mocks\data;

class MockPostObject 
{
	public $id;
	public $data;

	public function __construct($values) 
	{
		foreach($values as $key => $value) {
			$this->$key = $value;
		}
	}
}