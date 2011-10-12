<?php

namespace arthur\core;

use arthur\util\Set;

class Environment 
{
	protected static $_configurations = array(
		'production'  => array(),
		'development' => array(),
		'test'        => array()
	);

	protected static $_current = '';
	protected static $_detector = null;

	public static function reset() {
		static::$_current = '';
		static::$_detector = null;
		static::$_configurations = array(
			'production'  => array(),
			'development' => array(),
			'test'        => array()
		);
	}

	public static function is($detect) 
	{
		if(is_callable($detect)) 
			static::$_detector = $detect;

		return (static::$_current == $detect);
	}

	public static function get($name = null) 
	{
		$cur = static::$_current;

		if(!$name)
			return $cur;
		if($name === true) 
			return isset(static::$_configurations[$cur]) ? static::$_configurations[$cur] : null;
		if(isset(static::$_configurations[$name])) 
			return static::_processDotPath($name, static::$_configurations);
		if(!isset(static::$_configurations[$cur]))
			return static::_processDotPath($name, static::$_configurations);

		return static::_processDotPath($name, static::$_configurations[$cur]);
	}

	protected static function _processDotPath($path, &$arrayPointer) 
	{
		if(isset($arrayPointer[$path]))
			return $arrayPointer[$path];
		if(strpos($path, '.') === false)
			return null;    
			
		$pathKeys = explode('.', $path);
		foreach($pathKeys as $pathKey) 
		{
			if(!isset($arrayPointer[$pathKey])) 
				return false;
			$arrayPointer = &$arrayPointer[$pathKey];
		}  
		
		return $arrayPointer;
	}   
	
	public static function set($env, $config = null) 
	{
		if(is_null($config)) 
		{
			switch(true) 
			{
				case is_object($env) || is_array($env):
					static::$_current = static::_detector()->__invoke($env);
				break;
				case isset(static::$_configurations[$env]):
					static::$_current = $env;
				break;
			}
			return;
		}
		$env  = ($env === true) ? static::$_current : $env;
		$base = isset(static::$_configurations[$env]) ? static::$_configurations[$env] : array();       
		
		return static::$_configurations[$env] = Set::merge($base, $config);
	}

	protected static function _detector() 
	{
		return static::$_detector ?: function($request) 
		{
			$isLocal = in_array($request->env('SERVER_ADDR'), array('::1', '127.0.0.1'));
			$isCli = is_array($request->argv) && !empty($request->argv);
			switch(true) 
			{
				case (isset($request->params['env'])):
					return $request->params['env'];
				case ($isCli && $request->argv[0] == 'test'):
					return 'test';
				case ($isCli):
					return 'development';
				case (preg_match('/^test\//', $request->url) && $isLocal):
					return 'test';
				case ($isLocal):
					return 'development';
				case (preg_match('/^test/', $request->env('HTTP_HOST'))):
					return 'test';
				default:
					return 'production';
			}
		};
	}
}