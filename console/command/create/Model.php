<?php

namespace arthur\console\command\create;

use arthur\util\Inflector;

class Model extends \arthur\console\command\Create 
{
	protected function _class($request) 
	{
		return Inflector::camelize(Inflector::pluralize($request->action));
	}
}