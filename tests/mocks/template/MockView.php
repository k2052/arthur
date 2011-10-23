<?php

namespace arthur\tests\mocks\template;

class MockView extends \arthur\template\View 
{
	public function renderer() 
	{
		return $this->_config['renderer'];
	}
}