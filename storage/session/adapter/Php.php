<?php

namespace arthur\storage\session\adapter;

use arthur\util\Set;
use RuntimeException;
use arthur\core\ConfigException;

class Php extends \arthur\core\Object 
{
	protected $_defaults = array(
		'session.cookie_lifetime' => '0', 'session.cookie_httponly' => true
	);

	public function __construct(array $config = array()) 
	{
		parent::__construct($config + $this->_defaults);
	}

	protected function _init() 
	{
		$config = $this->_config;
		unset($config['adapter'], $config['strategies'], $config['filters'], $config['init']);

		if(!isset($config['session.name']))
			$config['session.name'] = basename(ARTHUR_APP_PATH); 
			
		foreach($config as $key => $value) 
		{
			if(strpos($key, 'session.') === false)
				continue;
			if(ini_set($key, $value) === false)
				throw new ConfigException("Could not initialize the session.");
		}
	}

	protected static function _start() 
	{
		if(session_id())
			return true;
		if(!isset($_SESSION))
			session_cache_limiter('nocache');

		return session_start();
	}

	public static function isStarted() 
	{
		return (boolean) session_id();
	}

	public static function key($key = null) 
	{
		if($key) return session_id($key);

		return session_id() ?: null;
	}

	public static function check($key, array $options = array()) 
	{
		if(!static::isStarted() && !static::_start())
			throw new RuntimeException("Could not start session.");

		return function($self, $params) 
		{
			return Set::check($_SESSION, $params['key']);
		};
	}

	public static function read($key = null, array $options = array()) 
	{
		if(!static::isStarted() && !static::_start())
			throw new RuntimeException("Could not start session.");
		return function($self, $params) 
		{
			$key = $params['key'];

			if(!$key) return $_SESSION;
			if(strpos($key, '.') === false)
				return isset($_SESSION[$key]) ? $_SESSION[$key] : null;  
				
			$filter = function($keys, $data) use (&$filter) 
			{
				$key = array_shift($keys);  
				
				if(isset($data[$key]))
					return (empty($keys)) ? $data[$key] : $filter($keys, $data[$key]);
			};  
			
			return $filter(explode('.', $key), $_SESSION);
		};
	}
	public static function write($key, $value, array $options = array()) 
	{
		if(!static::isStarted() && !static::_start())
			throw new RuntimeException("Could not start session.");
		$class = __CLASS__;

		return function($self, $params) use ($class) 
		{
			return $class::overwrite(
				$_SESSION, Set::insert($_SESSION, $params['key'], $params['value'])
			);
		};
	}

	public static function delete($key, array $options = array()) 
	{
		if(!static::isStarted() && !static::_start())
			throw new RuntimeException("Could not start session.");
		$class = __CLASS__;

		return function($self, $params) use ($class) 
		{
			$key = $params['key'];
			$class::overwrite($_SESSION, Set::remove($_SESSION, $key));  
			
			return !Set::check($_SESSION, $key);
		};
	}

	public function clear(array $options = array()) 
	{
		if(!static::isStarted() && !static::_start())
			throw new RuntimeException("Could not start session.");

		return function($self, $params) 
		{
			return session_destroy();
		};
	}

	public static function enabled() 
	{
		return (boolean) session_id();
	}

	public static function overwrite(&$old, $new) 
	{
		if(!empty($old)) 
		{
			foreach($old as $key => $value) 
			{
				if(!isset($new[$key]))
					unset($old[$key]);
			}
		}
		foreach($new as $key => $value) {
			$old[$key] = $value;
		}   
		
		return true;
	}
}