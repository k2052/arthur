<?php

namespace lithium\analysis;

use UnexpectedValueException;

class Logger extends \lithium\core\Adaptable 
{
	protected static $_configurations = array();
	protected static $_adapters = 'adapter.analysis.logger';
	protected static $_priorities = array(
		'emergency' => 0,
		'alert'     => 1,
		'critical'  => 2,
		'error'     => 3,
		'warning'   => 4,
		'notice'    => 5,
		'info'      => 6,
		'debug'     => 7
	);

	public static function write($priority, $message, array $options = array()) 
	{
		$defaults = array('name' => null);
		$options += $defaults;
		$result  = true;

		if($name = $options['name'])
			$methods = array($name => static::adapter($name)->write($priority, $message, $options));
		elseif(!isset(static::$_priorities[$priority])) {
			$message = "Attempted to write log message with invalid priority `{$priority}`.";
			throw new UnexpectedValueException($message);    
		}
		else
			$methods = static::_configsByPriority($priority, $message, $options);

		foreach($methods as $name => $method) 
		{
			$params = compact('priority', 'message', 'options');
			$config = static::_config($name);
			$result &= static::_filter(__FUNCTION__, $params, $method, $config['filters']);
		}  
		
		return $methods ? $result : false;
	}

	public static function __callStatic($priority, $params) 
	{
		$params += array(null, array());     
		
		return static::write($priority, $params[0], $params[1]);
	}

	protected static function _initConfig($name, $config) 
	{
		$defaults = array('priority' => true);  
		
		return parent::_initConfig($name, $config) + $defaults;
	}

	protected static function _configsByPriority($priority, $message, array $options = array()) 
	{
		$configs = array();
		$key    = 'priority';

		foreach (array_keys(static::$_configurations) as $name) 
		{
			$config     = static::config($name);
			$nameMatch  = ($config[$key] === true || $config[$key] === $priority);
			$arrayMatch = (is_array($config[$key]) && in_array($priority, $config[$key]));

			if($nameMatch || $arrayMatch) {
				$method = static::adapter($name)->write($priority, $message, $options);
				$method ? $configs[$name] = $method : null;
			}
		}    
		
		return $configs;
	}
}