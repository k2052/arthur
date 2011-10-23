<?php

namespace arthur\tests\mocks\core;

class MockMethodFiltering extends \arthur\core\Object 
{

	public function method($data) 
	{
		$data[] = 'Starting outer method call';  
		
		$result = $this->_filter(__METHOD__, compact('data'), function($self, $params, $chain) 
		{
			$params['data'][] = 'Inside method implementation';
			return $params['data'];
		});
		$result[] = 'Ending outer method call';   
		
		return $result;
	}

	public function method2() 
	{
		$filters =& $this->_methodFilters;
		$method = function($self, $params, $chain) use (&$filters) 
		{
			return $filters;
		};              
		
		return $this->_filter(__METHOD__, array(), $method);
	}
}