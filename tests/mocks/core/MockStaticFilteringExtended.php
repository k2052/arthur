<?php

namespace arthur\tests\mocks\core;

class MockStaticFilteringExtended extends \arthur\tests\mocks\core\MockStaticMethodFiltering 
{
	public static function childMethod() 
	{
		return 'Working';
	}
}