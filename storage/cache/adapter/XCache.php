<?php

namespace arthur\storage\cache\adapter;

class XCache extends \arthur\core\Object 
{
	public function __construct(array $config = array()) 
	{
		$defaults = array('prefix' => '', 'expiry' => '+1 hour');
		parent::__construct($config + $defaults);
	}

	public function write($key, $data, $expiry = null) 
	{
		$expiry = ($expiry) ?: $this->_config['expiry'];

		return function($self, $params) use ($expiry) 
		{
			return xcache_set($params['key'], $params['data'], strtotime($expiry) - time());
		};
	}

	public function read($key) 
	{
		return function($self, $params) 
		{
			return xcache_get($params['key']);
		};
	}

	public function delete($key) 
	{
		return function($self, $params) 
		{
			return xcache_unset($params['key']);
		};
	}

	public function decrement($key, $offset = 1) 
	{
		return function($self, $params) use ($offset) 
		{
			return xcache_dec($params['key'], $offset);
		};
	}
	
	public function increment($key, $offset = 1) 
	{
		return function($self, $params) use ($offset) 
		{
			extract($params);
			return xcache_inc($params['key'], $offset);
		};
	}

	public function clear() 
	{
		$admin = (ini_get('xcache.admin.enable_auth') === "On");
		if($admin && (!isset($this->_config['username']) || !isset($this->_config['password'])))
			return false;
		$credentials = array();

		if(isset($_SERVER['PHP_AUTH_USER'])) {
			$credentials['username'] = $_SERVER['PHP_AUTH_USER'];
			$_SERVER['PHP_AUTH_USER'] = $this->_config['username'];
		}
		if(isset($_SERVER['PHP_AUTH_PW'])) {
			$credentials['password'] = $_SERVER['PHP_AUTH_PW'];
			$_SERVER['PHP_AUTH_PW'] = $this->_config['pass'];
		}

		for($i = 0, $max = xcache_count(XC_TYPE_VAR); $i < $max; $i++) {
			if(xcache_clear_cache(XC_TYPE_VAR, $i) === false)
				return false;
		}

		if(isset($_SERVER['PHP_AUTH_USER'])) {
			$_SERVER['PHP_AUTH_USER'] =
				($credentials['username'] !== null) ? $credentials['username'] : null;
		}
		if(isset($_SERVER['PHP_AUTH_PW'])) {
			$_SERVER['PHP_AUTH_PW'] =
				($credentials['password'] !== null) ? $credentials['password'] : null;
		}  
		
		return true;
	}

	public static function enabled() 
	{
		return extension_loaded('xcache');
	}
}