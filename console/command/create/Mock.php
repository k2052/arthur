<?php

namespace arthur\console\command\create;

use arthur\util\Inflector;

class Mock extends \arthur\console\command\Create 
{
	protected function _namespace($request, $options = array()) 
	{
		$request->params['command'] = $request->action;
		return parent::_namespace($request, array('prepend' => 'tests.mocks.'));
	}

	protected function _parent($request) 
	{
		$namespace = parent::_namespace($request);
		$class = Inflector::pluralize($request->action);
		return "\\{$namespace}\\{$class}";
	}

	protected function _class($request) 
	{
		$type = $request->action;
		$name = $request->args();

		if($command = $this->_instance($type)) {
			$request->params['action'] = $name;
			$name = $command->invokeMethod('_class', array($request));
		}    
		
		return  Inflector::pluralize("Mock{$name}");
	}       
	
	protected function _methods($request) 
	{
		return null;
	}
}