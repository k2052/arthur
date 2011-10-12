<?php

namespace arthur\storage\session\adapter;

use arthur\util\Set;
use arthur\util\Inflector;

class Cookie extends \arthur\core\Object 
{
	protected $_defaults = array(
		'expire' => '+2 days', 'path' => '/', 'name' => null,
		'domain' => '', 'secure' => false, 'httponly' => false
	);
	
	public function __construct(array $config = array()) 
	{
		parent::__construct($config + $this->_defaults);
	}

	protected function _init() 
	{
		parent::_init();

		if(!$this->_config['name'])
			$this->_config['name'] = Inflector::slug(basename(LITHIUM_APP_PATH)) . 'cookie';
	}

	public function key() 
	{
		return $this->_config['name'];
	}

	public function isEnabled() 
	{
		return true;
	}

	public function isStarted() 
	{
		return (isset($_COOKIE));
	}

	public function check($key) 
	{
		$config = $this->_config;

		return function($self, $params) use (&$config) {
			return (isset($_COOKIE[$config['name']][$params['key']]));
		};
	}

	public function read($key = null, array $options = array()) 
	{
		$config = $this->_config;

		return function($self, $params) use (&$config) 
		{
			$key = $params['key'];
			if(!$key) 
			{
				if(isset($_COOKIE[$config['name']]))
					return $_COOKIE[$config['name']];

				return array();
			}
			if(strpos($key, '.') !== false) 
			{
				$key    = explode('.', $key);
				$result = (isset($_COOKIE[$config['name']])) ? $_COOKIE[$config['name']] : array();

				foreach($key as $k) 
				{
					if(!isset($result[$k]))
						return null;
					$result = $result[$k];
				}
				return $result;
			}
			if(isset($_COOKIE[$config['name']][$key]))
				return $_COOKIE[$config['name']][$key];
		};
	}

	public function write($key, $value = null, array $options = array()) 
	{
		$expire      = (!isset($options['expire']) && empty($this->_config['expire']));
		$config      = $this->_config;
		$cookieClass = __CLASS__;

		if($expire && $key != $config['name']) 
			return null;
		$expires = (isset($options['expire'])) ? $options['expire'] : $config['expire'];

		return function($self, $params) use (&$config, &$expires, $cookieClass) 
		{
			$key   = $params['key'];
			$value = $params['value'];
			$key   = array($key => $value);
			if(is_array($value))
				$key = Set::flatten($key);

			foreach($key as $name => $val) 
			{
				$name   = $cookieClass::keyFormat($name, $config);
				$result = setcookie($name, $val, strtotime($expires), $config['path'],
					$config['domain'], $config['secure'], $config['httponly']
				);

				if(!$result)
					throw new RuntimeException("There was an error setting {$name} cookie.");
			}     
			
			return true;
		};
	}

	public function delete($key, array $options = array()) 
	{
		$config      = $this->_config;
		$cookieClass = get_called_class();

		return function($self, $params) use (&$config, $cookieClass) 
		{
			$key     = $params['key'];
			$path    = '/' . str_replace('.', '/', $config['name'] . '.' . $key) . '/.';
			$cookies = current(Set::extract($_COOKIE, $path));
			if(is_array($cookies)) 
			{
				$cookies = array_keys(Set::flatten($cookies));
				foreach($cookies as &$name) {
					$name = $key . '.' . $name;
				}
			} 
			else
				$cookies = array($key);     
				
			foreach($cookies as &$name) 
			{
				$name   = $cookieClass::keyFormat($name, $config);
				$result = setcookie($name, "", 1, $config['path'],
					$config['domain'], $config['secure'], $config['httponly']
				);
				if(!$result) 
					throw new RuntimeException("There was an error deleting {$name} cookie.");
			}    
			
			return true;
		};
	}

	public function clear(array $options = array()) 
	{
		$options     += array('destroySession' => true);
		$config      = $this->_config;
		$cookieClass = get_called_class();

		return function($self, $params) use (&$config, $options, $cookieClass) 
		{
			if($options['destroySession'] && session_id())
				session_destroy();
			if(!isset($_COOKIE[$config['name']]))
				return true;      
				
			$cookies = array_keys(Set::flatten($_COOKIE[$config['name']]));
			foreach($cookies as $name) 
			{
				$name   = $cookieClass::keyFormat($name, $config);
				$result = setcookie($name, "", 1, $config['path'],
					$config['domain'], $config['secure'], $config['httponly']
				);
				if(!$result)
					throw new RuntimeException("There was an error clearing {$cookie} cookie.");
			}
			unset($_COOKIE[$config['name']]);       
			
			return true;
		};
	}

	public static function keyFormat($name, $config) 
	{
		return $config['name'] . '[' . str_replace('.', '][', $name) . ']';
	}
}