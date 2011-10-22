<?php

namespace arthur\console\command\create;

use arthur\util\Inflector;

class Controller extends \arthur\console\command\Create 
{
	protected function _use($request) 
	{
		$request->params['command'] = 'model';
		return $this->_namespace($request) . '\\' . $this->_model($request);
	}

	protected function _class($request) 
	{
		return $this->_name($request) . 'Controller';
	}

	protected function _name($request) 
	{
		return Inflector::camelize(Inflector::pluralize($request->action));
	}

	protected function _plural($request) 
	{
		return Inflector::pluralize(Inflector::camelize($request->action, false));
	}

	protected function _model($request) 
	{
		return Inflector::camelize(Inflector::pluralize($request->action));
	}

	protected function _singular($request) 
	{
		return Inflector::singularize(Inflector::camelize($request->action, false));
	}
}