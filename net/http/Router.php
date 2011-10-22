<?php

namespace arthur\net\http;

use arthur\util\Inflector;
use arthur\net\http\RoutingException;

class Router extends \arthur\core\StaticObject 
{
	protected static $_configurations = array();
	protected static $_classes = array(
		'route' => 'arthur\net\http\Route'
	);

	public static function config($config = array()) 
	{
		if(!$config)
			return array('classes' => static::$_classes);
		if(isset($config['classes']))
			static::$_classes = $config['classes'] + static::$_classes;
	}

	public static function connect($template, $params = array(), $options = array()) 
	{
		if(!is_object($template)) 
		{
			if(is_string($params))
				$params = static::_parseString($params, false);
			if(isset($params[0]) && is_array($tmp = static::_parseString($params[0], false))) {
				unset($params[0]);
				$params = $tmp + $params;
			}
			if(is_callable($options))
				$options = array('handler' => $options);

			$class = static::$_classes['route'];
			$template = new $class(compact('template', 'params') + $options);
		}          
		
		return (static::$_configurations[] = $template);
	}

	public static function process($request) 
	{
		if(!$result = static::parse($request))
			return $request;

		return $result;
	}

	public static function parse($request) 
	{
		$orig = $request->params;
		$url  = $request->url;

		foreach(static::$_configurations as $route) 
		{
			if(!$match = $route->parse($request, compact('url')))
				continue;

			$request = $match;

			if($route->canContinue() && isset($request->params['args'])) 
			{
				$url = '/' . join('/', $request->params['args']);
				unset($request->params['args']);
				continue;
			} 
			
			return $request;
		}    
		
		$request->params = $orig;
	}

	public static function match($url = array(), $context = null, array $options = array()) 
	{
		if(is_string($url = static::_prepareParams($url, $context, $options)))
			return $url;

		$defaults = array('action' => 'index');
		$url     += $defaults;
		$stack    = array();

		$base   = isset($context) ? $context->env('base') : '';
		$suffix = isset($url['#']) ? "#{$url['#']}" : null;
		unset($url['#']);

		foreach(static::$_configurations as $route) 
		{
			if(!$match = $route->match($url, $context)) 
				continue;

			if($route->canContinue()) 
			{
				$stack[] = $match;
				$export  = $route->export();
				$keys    = $export['match'] + $export['keys'] + $export['defaults'];
				unset($keys['args']);
				$url = array_diff_key($url, $keys);
				continue;
			}
			if($stack) {
				$stack[] = $match;
				$match = static::_compileStack($stack);
			}
			$path = rtrim("{$base}{$match}{$suffix}", '/') ?: '/';
			$path = ($options) ? static::_prefix($path, $context, $options) : $path;       
			
			return $path ?: '/';
		}
		$url = static::_formatError($url);
		throw new RoutingException("No parameter match found for URL `{$url}`.");
	}

	protected static function _compileStack($stack) 
	{
		$result = null;

		foreach(array_reverse($stack) as $fragment) 
		{
			if($result) {
				$result = str_replace('{:args}', ltrim($result, '/'), $fragment);
				continue;
			}
			$result = $fragment;
		}  
		
		return $result;
	}

	protected static function _formatError($url) 
	{
		$match = array("\n", 'array (', ',)', '=> NULL', '(  \'', ',  ');
		$replace = array('', '(', ')', '=> null', '(\'', ', ');
		return str_replace($match, $replace, var_export($url, true));
	}

	protected static function _prepareParams($url, $context, array $options) 
	{
		if(is_string($url)) 
		{
			if(strpos($url, '://')) return $url;   
			
			foreach(array('#', '//', 'mailto') as $prefix) {
				if(strpos($url, $prefix) === 0) return $url;
			}
			if(is_string($url = static::_parseString($url, $context))) {
				return static::_prefix($url, $context, $options);
			}
		}
		if(isset($url[0]) && is_array($params = static::_parseString($url[0], $context))) {
			unset($url[0]);
			$url = $params + $url;
		}          
		
		return static::_persist($url, $context);
	}

	protected static function _prefix($path, $context = null, array $options = array()) 
	{
		$defaults = array('scheme' => null, 'host' => null, 'absolute' => false);

		if($context) {
			$defaults['host'] = $context->env('HTTP_HOST');
			$defaults['scheme'] = $context->env('HTTPS') ? 'https://' : 'http://';
		}
		$options += $defaults;

		return ($options['absolute']) ? "{$options['scheme']}{$options['host']}{$path}" : $path;
	}

	protected static function _persist($url, $context) 
	{
		if(!$context || !isset($context->persist))
			return $url;

		foreach($context->persist as $key) {
			$url += array($key => $context->params[$key]);
			if($url[$key] === null) unset($url[$key]);
		} 
		
		return $url;
	}

	public static function get($route = null) 
	{
		if($route === null)
			return static::$_configurations;

		return isset(static::$_configurations[$route]) ? static::$_configurations[$route] : null;
	}

	public static function reset() 
	{
		static::$_configurations = array();
	}

	protected static function _parseString($path, $context) 
	{
		if(!preg_match('/^[A-Za-z0-9_]+::[A-Za-z0-9_]+$/', $path)) 
		{
			$base = $context ? $context->env('base') : '';
			$path = trim($path, '/');     
			
			return $context !== false ? "{$base}/{$path}" : null;
		}                  
		
		list($controller, $action) = explode('::', $path, 2);
		$controller = Inflector::underscore($controller);    
		
		return compact('controller', 'action');
	}
}