<?php

namespace arthur\storage\cache\adapter;

use Redis as RedisCore;

class Redis extends \arthur\core\Object 
{
	public $connection;

	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'host'       => '127.0.0.1:6379',
			'expiry'     => '+1 hour',
			'persistent' => false
		); 
		
		parent::__construct($config + $defaults);
	}

	protected function _init() 
	{
		if(!$this->connection)
			$this->connection = new RedisCore();

		list($ip, $port) = explode(':', $this->_config['host']);
		$method = $this->_config['persistent'] ? 'pconnect' : 'connect';
		$this->connection->{$method}($ip, $port);
	}


	protected function _ttl($key, $expiry) 
	{
		return $this->connection->expireAt($key, is_int($expiry) ? $expiry : strtotime($expiry));
	}

	public function write($key, $value = null, $expiry = null) 
	{
		$connection =& $this->connection;
		$expiry     = ($expiry) ?: $this->_config['expiry'];
		$_self      =& $this;

		return function($self, $params) use (&$_self, &$connection, $expiry) 
		{
			if(is_array($params['key'])) 
			{
				$expiry = $params['data'];

				if($connection->mset($params['key'])) 
				{
					$ttl = array();

					if($expiry) {
						foreach($params['key'] as $k => $v) {
							$ttl[$k] = $_self->invokeMethod('_ttl', array($k, $expiry));
						}
					}  
					
					return $ttl;
				}
			}
			if($result = $connection->set($params['key'], $params['data'])) 
			{
				if($expiry)
					return $_self->invokeMethod('_ttl', array($params['key'], $expiry));

				return $result;
			}
		};
	}

	public function read($key) 
	{
		$connection =& $this->connection;

		return function($self, $params) use (&$connection) 
		{
			$key = $params['key'];

			if(is_array($key))
				return $connection->getMultiple($key);
			
			return $connection->get($key);
		};
	}

	public function delete($key) 
	{
		$connection =& $this->connection;

		return function($self, $params) use (&$connection) {
			return (boolean) $connection->delete($params['key']);
		};
	}

	public function decrement($key, $offset = 1) 
	{
		$connection =& $this->connection;

		return function($self, $params) use (&$connection, $offset) 
		{
			return $connection->decr($params['key'], $offset);
		};
	}
	
	public function increment($key, $offset = 1) 
	{
		$connection =& $this->connection;

		return function($self, $params) use (&$connection, $offset) 
		{
			return $connection->incr($params['key'], $offset);
		};
	}

	public function clear() 
	{
		return $this->connection->flushdb();
	}

	public static function enabled() 
	{
		return extension_loaded('redis');
	}
}