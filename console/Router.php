<?php

namespace arthur\console;

class Router extends \arthur\core\Object 
{
	public static function parse($request = null) 
	{
		$defaults = array('command' => null, 'action' => 'run', 'args' => array());
		$params   = $request ? (array) $request->params + $defaults : $defaults;

		if(!empty($request->argv)) 
		{
			$args = $request->argv;

			while($arg = array_shift($args)) 
			{
				if(preg_match('/^-(?P<key>[a-zA-Z0-9])$/i', $arg, $match)) {
					$params[$match['key']] = true;
					continue;
				}
				if(preg_match('/^--(?P<key>[a-z0-9-]+)(?:=(?P<val>.+))?$/i', $arg, $match)) {
					$params[$match['key']] = !isset($match['val']) ? true : $match['val'];
					continue;
				}   
				
				$params['args'][] = $arg;
			}
		}       
		
		foreach(array('command', 'action') as $param) 
		{
			if(!empty($params['args'])) {
				$params[$param] = array_shift($params['args']);
		}  
		
		return $params;
	}
}