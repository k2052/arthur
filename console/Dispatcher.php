<?php

namespace arthur\console;

use arthur\core\Libraries;
use arthur\core\Environment;
use UnexpectedValueException;

class Dispatcher extends \arthur\core\StaticObject 
{
	protected static $_classes = array(
		'request' => 'arthur\console\Request',
		'router'  => 'arthur\console\Router'
	);

	protected static $_rules = array(
		'command' => array(array('arthur\util\Inflector', 'camelize')),
		'action'  => array(array('arthur\util\Inflector', 'camelize', array(false)))
	);

	public static function config($config = array()) 
	{
		if(!$config)
			return array('rules' => static::$_rules); 
			
		foreach($config as $key => $val) {
			if(isset(static::${'_' . $key}))
				static::${'_' . $key} = $val + static::${'_' . $key};
		}
	}

	public static function run($request = null, $options = array()) 
	{
		$defaults = array('request' => array());
		$options += $defaults;
		$classes  = static::$_classes;
		$params   = compact('request', 'options');

		return static::_filter(__FUNCTION__, $params, function($self, $params) use ($classes) 
		{
			$request = $params['request'];
			$options = $params['options'];

			$router          = $classes['router'];
			$request         = $request ?: new $classes['request']($options['request']);
			$request->params = $router::parse($request);

			$params = $self::applyRules($request->params);

			try {
				$callable = $self::invokeMethod('_callable', array($request, $params, $options));
				return $self::invokeMethod('_call', array($callable, $request, $params));
			} 
			catch(UnexpectedValueException $e) {
				return (object) array('status' => $e->getMessage() . "\n");
			}
		});
	}

	protected static function _callable($request, $params, $options) 
	{
		$params = compact('request', 'params', 'options');      
		
		return static::_filter(__FUNCTION__, $params, function($self, $params) 
		{
			$request = $params['request'];
			$params  = $params['params'];
			$name    = $params['command'];

			if(!$name) {
				$request->params['args'][0] = $name;
				$name = 'arthur\console\command\Help';
			}
			if(class_exists($class = Libraries::locate('command', $name)))
				return new $class(compact('request'));

			throw new UnexpectedValueException("Command `{$name}` not found.");
		});
	}

	public static function applyRules($params) 
	{
		$result = array();

		if(!$params)
			return false;

		foreach(static::$_rules as $name => $rules) 
		{
			foreach($rules as $rule) 
			{
				if(!empty($params[$name]) && isset($rule[0])) 
				{
					$options = array_merge(
						array($params[$name]), isset($rule[2]) ? (array) $rule[2] : array()
					);       
					
					$result[$name] = call_user_func_array(array($rule[0], $rule[1]), $options);
				}
			}
		}         
		
		return $result + array_diff_key($params, $result);
	}

	protected static function _call($callable, $request, $params) 
	{
		$params = compact('callable', 'request', 'params');
		Environment::set($request);     
		
		return static::_filter(__FUNCTION__, $params, function($self, $params) 
		{
			if(is_callable($callable = $params['callable'])) 
			{
				$request = $params['request'];
				$params  = $params['params'];

				if(!method_exists($callable, $params['action'])) {
					array_unshift($params['args'], $request->params['action']);
					$params['action'] = 'run';
				}
				$isHelp = (
					!empty($params['help']) || !empty($params['h'])
					|| !method_exists($callable, $params['action'])
				);   
				
				if($isHelp)
					$params['action'] = '_help';

				return $callable($params['action'], $params['args']);
			}         
			
			throw new UnexpectedValueException("Callable `{$callable}` is actually not callable.");
		});
	}
}