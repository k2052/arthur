<?php

namespace arthur\storage\cache\adapter;

class Apc extends \arthur\core\Object 
{
	public function __construct(array $config = array())
	{
		$defaults = array(
			'prefix' => '',
			'expiry' => '+1 hour'
		);     
		
		parent::__construct($config + $defaults);
	}

	public function write($key, $data, $expiry = null) 
	{
		$expiry = ($expiry) ?: $this->_config['expiry'];

		return function($self, $params) use ($expiry) 
		{
			$cachetime = (is_int($expiry) ? $expiry : strtotime($expiry)) - time();
			$key = $params['key'];

			if(is_array($key))
				return apc_store($key, $cachetime);
			
			return apc_store($params['key'], $params['data'], $cachetime);
		};
	}

	public function read($key) 
	{
		return function($self, $params) {
			return apc_fetch($params['key']);
		};
	}

	public function delete($key) 
	{
		return function($self, $params) {
			return apc_delete($params['key']);
		};
	}
	
	public function decrement($key, $offset = 1) 
	{
		return function($self, $params) use ($offset) {
			return apc_dec($params['key'], $offset);
		};
	}

	public function increment($key, $offset = 1) 
	{
		return function($self, $params) use ($offset) 
		{
			return apc_inc($params['key'], $offset);
		};
	}

	public function clear() 
	{
		return apc_clear_cache('user');
	}

	public static function enabled() 
	{
		$loaded = extension_loaded('apc');
		$isCli = (php_sapi_name() === 'cli');
		$enabled = (!$isCli && ini_get('apc.enabled')) || ($isCli && ini_get('apc.enable_cli'));
		return ($loaded && $enabled);
	}
}