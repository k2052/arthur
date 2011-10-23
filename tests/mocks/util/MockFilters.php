<?php

namespace arthur\tests\mocks\util;

class MockFilters extends \arthur\core\StaticObject 
{
	public static function filteredMethod() 
	{
		return static::_filter(__FUNCTION__, array(), function($self, $params) {
			return 'Working?';
		});
	}
}