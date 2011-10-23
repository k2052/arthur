<?php

namespace arthur\tests\mocks\action;

use stdClass;

class MockDispatcher extends \arthur\action\Dispatcher 
{
	public static $dispatched = array();

	protected static function _callable($request, $params, $options) 
	{
		$callable         = new stdClass();
		$callable->params = $params; 
		
		return $callable;
	}

	protected static function _call($callable, $request, $params) 
	{
		if(is_callable($callable->params['controller']))
			return parent::_call($callable->params['controller'], $request, $params);

		static::$dispatched[] = $callable;
	}
}