<?php

namespace arthur\security;

use arthur\core\ConfigException;

class Auth extends \arthur\core\Adaptable 
{
	protected static $_configurations = array();
	protected static $_adapters = 'adapter.security.auth';

	protected static $_classes = array(
		'session' => 'arthur\storage\Session'
	);                             
	
	protected static function _initConfig($name, $config) 
	{
		$defaults = array('session' => array(
			'key'     => $name,
			'class'   => static::$_classes['session'],
			'options' => array()
		));                                                    
		
		$config = parent::_initConfig($name, $config) + $defaults;
		$config['session'] += $defaults['session'];     
		
		return $config;
	}

	public static function check($name, $credentials = null, array $options = array()) 
	{
		$defaults = array('checkSession' => true, 'writeSession' => true);
		$options += $defaults;
		$params   = compact('name', 'credentials', 'options');

		return static::_filter(__FUNCTION__, $params, function($self, $params) 
		{
			extract($params);
			$config = $self::invokeMethod('_config', array($name));

			if($config === null)
				throw new ConfigException("Configuration `{$name}` has not been defined.");

			$session = $config['session'];

			if($options['checkSession']) 
			{
				if($data = $session['class']::read($session['key'], $session['options']))
					return $data;
			}

			if(($credentials) && $data = $self::adapter($name)->check($credentials, $options))
				return ($options['writeSession']) ? $self::set($name, $data) : $data;

			return false;
		});
	}

	public static function set($name, $data, array $options = array()) 
	{
		$params = compact('name', 'data', 'options');

		return static::_filter(__FUNCTION__, $params, function($self, $params) 
		{
			extract($params);
			$config = $self::invokeMethod('_config', array($name));
			$session = $config['session'];

			if($data = $self::adapter($name)->set($data, $options)) 
			{
				$session['class']::write($session['key'], $data, $options + $session['options']);     
				
				return $data;
			}   
			
			return false;
		});
	}

	public static function clear($name, array $options = array()) 
	{
		$defaults = array('clearSession' => true);
		$options += $defaults;

		return static::_filter(__FUNCTION__, compact('name', 'options'), function($self, $params) 
		{
			extract($params);
			$config  = $self::invokeMethod('_config', array($name));
			$session = $config['session'];

			if($options['clearSession']) 
				$session['class']::delete($session['key'], $session['options']);

			$self::adapter($name)->clear($options);
		});
	}
}