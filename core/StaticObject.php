<?php

namespace arthur\core;

use arthur\core\Libraries;
use arthur\util\collection\Filters;

class StaticObject 
{
  protected static $_methodFilters = array();
	protected static $_parents = array();
	
	public static function applyFilter($method, $filter = null) 
	{
		$class = get_called_class();
		foreach((array) $method as $m) 
		{
			if(!isset(static::$_methodFilters[$class][$m])) 
  			static::$_methodFilters[$class][$m] = array();

			static::$_methodFilters[$class][$m][] = $filter;
		}
	}  
	
	public static function invokeMethod($method, $params = array()) 
	{
		switch(count($params)) 
		{
			case 0:
				return static::$method();
			case 1:
				return static::$method($params[0]);
			case 2:
				return static::$method($params[0], $params[1]);
			case 3:
				return static::$method($params[0], $params[1], $params[2]);
			case 4:
				return static::$method($params[0], $params[1], $params[2], $params[3]);
			case 5:
				return static::$method($params[0], $params[1], $params[2], $params[3], $params[4]);
			default:
				return forward_static_call_array(array(get_called_class(), $method), $params);
		}
	}   
	
	protected static function _instance($name, array $options = array()) 
	{
		if(is_string($name) && isset(static::$_classes[$name]))
			$name = static::$_classes[$name];

		return Libraries::instance(null, $name, $options);
	}       
	
	protected static function _filter($method, $params, $callback, $filters = array()) 
	{
		$class = get_called_class();
		$hasNoFilters = empty(static::$_methodFilters[$class][$method]);

		if($hasNoFilters && !$filters && !Filters::hasApplied($class, $method)) 
			return $callback($class, $params, null);

		if(!isset(static::$_methodFilters[$class][$method])) {
			static::$_methodFilters += array($class => array());
			static::$_methodFilters[$class][$method] = array();
		}                                        
		
		$data = array_merge(static::$_methodFilters[$class][$method], $filters, array($callback)); 
		
		return Filters::run($class, $params, compact('data', 'class', 'method'));
	} 
	
	protected static function _parents() 
	{
		$class = get_called_class();

		if(!isset(self::$_parents[$class]))
			self::$_parents[$class] = class_parents($class);

		return self::$_parents[$class];
	}    
	
	protected static function _stop($status = 0) 
	{
		exit($status);
	}
}