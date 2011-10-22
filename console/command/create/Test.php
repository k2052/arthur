<?php

namespace arthur\console\command\create;

use arthur\core\Libraries;
use arthur\util\Inflector;
use arthur\analysis\Inspector;
use arthur\core\ClassNotFoundException;

class Test extends \arthur\console\command\Create 
{
	protected function _namespace($request, $options = array()) 
	{
		$request->params['command'] = $request->action;
		return parent::_namespace($request, array('prepend' => 'tests.cases.'));
	}

	protected function _use($request) 
	{
		return parent::_namespace($request) . '\\' . $this->_name($request);
	}

	protected function _class($request) 
	{
		$name = $this->_name($request);
		return  Inflector::classify("{$name}Test");
	}

	protected function _methods($request) 
	{
		$use  = $this->_use($request);
		$path = Libraries::path($use);

		if(!file_exists($path))
			return "";

		$methods     = (array) Inspector::methods($use, 'extents');
		$testMethods = array();

		foreach(array_keys($methods) as $method) {
			$testMethods[] = "\tpublic function test" . ucwords($method) . "() {}";
		}    
		
		return join("\n", $testMethods);
	}

	protected function _name($request) 
	{
		$type = $request->action;
		$name = $request->args();

		try {
			$command = $this->_instance($type);
		} 
		catch (ClassNotFoundException $e) {
			$command = null;
		}

		if($command) {
			$request->params['action'] = $name;
			$name = $command->invokeMethod('_class', array($request));
		}       
		
		$request->params['action'] = $type;
		return $name;
	}
}