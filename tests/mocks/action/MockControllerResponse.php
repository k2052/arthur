<?php

namespace arthur\tests\mocks\action;

class MockControllerResponse extends \arthur\action\Response 
{
	public $hasRendered = false;

	public function render() 
	{
		$this->hasRendered = true;
	}
}