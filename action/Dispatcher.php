<?php

namespace arthur\action;

use arthur\util\String;
use arthur\util\Inflector;
use arthur\core\Libraries;
use arthur\action\DispatchException;
use arthur\core\ClassNotFoundException;

class Dispatcher extends \arthur\core\StaticObject 
{ 
  protected static $_classes = array(
		'router' => 'arthur\net\http\Router'
	);
	
	protected static $_rules = array();   
	
	public static function config(array $config = array()) 
	{
		if(!$config) 
			return array('rules' => static::$_rules);

		foreach($config as $key => $val) 
		{
			$key = "_{$key}";
			if(isset(static::${$key})) 
				static::${$key} = $val + static::${$key};
		}
	}

	public static function run($request, array $options = array()) 
	{
		$router = static::$_classes['router'];
		$params = compact('request', 'options');

		return static::_filter(__FUNCTION__, $params, function($self, $params) use ($router) 
		{
			$request = $params['request'];
			$options = $params['options'];

			if(($result = $router::process($request)) instanceof Response)
				return $result;

			$params = $self::applyRules($result->params);

			if(!$params) 
				throw new DispatchException('Could not route request.');    
				
			$callable = $self::invokeMethod('_callable', array($result, $params, $options)); 
			
			return $self::invokeMethod('_call', array($callable, $result, $params));
		});
	}

	public static function applyRules(&$params) 
	{
		$result = array();
		$values = array();

		if(!$params) 
			return false;

		if(isset($params['controller']) && is_string($params['controller'])) 
		{
			$controller = $params['controller'];

			if(strpos($controller, '.') !== false) 
			{
				list($library, $controller) = explode('.', $controller);
				$controller = $library . '.' . Inflector::camelize($controller);
				$params += compact('library');
			} 
			elseif (strpos($controller, '\\') === false) 
			{
				$controller = Inflector::camelize($controller);

				if(isset($params['library']))
					$controller = "{$params['library']}.{$controller}";
			}
			$values = compact('controller');
		}
		$values += $params;

		foreach(static::$_rules as $rule => $value) 
		{
			foreach($value as $k => $v) 
			{
				if(isset($values[$rule]))
					$result[$k] = String::insert($v, $values);

				$match = preg_replace('/\{:\w+\}/', '@', $v);
				$match = preg_replace('/@/', '.+', preg_quote($match, '/'));

				if(preg_match('/' . $match . '/i', $values[$k]))
					return false;
			}
		}      
		
		return $result + $values;
	}

	protected static function _callable($request, $params, $options) 
	{
		$params = compact('request', 'params', 'options');

		return static::_filter(__FUNCTION__, $params, function($self, $params) 
		{
			$options = array('request' => $params['request']) + $params['options'];
			$controller = $params['params']['controller'];

			try {
				return Libraries::instance('controllers', $controller, $options);
			} 
			catch (ClassNotFoundException $e) {
				throw new DispatchException("Controller `{$controller}` not found.", null, $e);
			}
		});
	}

	protected static function _call($callable, $request, $params) 
	{
		$params = compact('callable', 'request', 'params');  
		
		return static::_filter(__FUNCTION__, $params, function($self, $params) 
		{
			if(is_callable($callable = $params['callable'])) 
				return $callable($params['request'], $params['params']);

			throw new DispatchException('Result not callable.');
		});
	}
}