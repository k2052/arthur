<?php

namespace arthur\storage\cache\adapter;

use Memcached;
use arthur\util\Set;

class Memcache extends \arthur\core\Object 
{
	const CONN_DEFAULT_PORT = 11211;
	public $connection = null;

	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'expiry' => '+1 hour',
			'host'   => '127.0.0.1'
		);          
		
		parent::__construct(Set::merge($defaults, $config));
	}
	
	protected function _init() 
	{
		$this->connection = $this->connection ?: new Memcached();
		$servers          = array();

		if(isset($this->_config['servers'])) {
			$this->connection->addServers($this->_config['servers']);
			return;
		}  
		
		$this->connection->addServers($this->_formatHostList($this->_config['host']));
	}

	protected function _formatHostList($host) 
	{
		$fromString = function($host) 
		{
			if(strpos($host, ':')) {
				list($host, $port) = explode(':', $host);
				return array($host, intval($port));
			}      
			
			return array($host, Memcache::CONN_DEFAULT_PORT);
		};

		if(is_string($host))
			return array($fromString($host));
		$servers = array();

		while(list($server, $weight) = each($this->_config['host'])) 
		{
			if(is_string($weight)) {
				$servers[] = $fromString($weight);
				continue;
			}
			$server    = $fromString($server);
			$server[]  = $weight;
			$servers[] = $server;
		}  
		
		return $servers;
	}

	public function write($key, $value, $expiry = null) 
	{
		$connection =& $this->connection;
		$expiry = ($expiry) ?: $this->_config['expiry'];

		return function($self, $params) use (&$connection, $expiry) 
		{
			$expires = is_int($expiry) ? $expiry : strtotime($expiry);
			$key     = $params['key'];

			if(is_array($key))
				return $connection->setMulti($key, $expires);
			
			return $connection->set($key, $params['data'], $expires);
		};
	}

	public function read($key) 
	{
		$connection =& $this->connection;

		return function($self, $params) use (&$connection) 
		{
			$key = $params['key'];

			if(is_array($key))
				return $connection->getMulti($key);
			if(($result = $connection->get($key)) === false) {
				if($connection->getResultCode() === Memcached::RES_NOTFOUND)
					$result = null;
			}       
			
			return $result;
		};
	}

	public function delete($key) 
	{
		$connection =& $this->connection;

		return function($self, $params) use (&$connection) 
		{
			return $connection->delete($params['key']);
		};
	}

	public function decrement($key, $offset = 1) 
	{
		$connection =& $this->connection;

		return function($self, $params) use (&$connection, $offset) 
		{
			return $connection->decrement($params['key'], $offset);
		};
	}

	public function increment($key, $offset = 1) 
	{
		$connection =& $this->connection;

		return function($self, $params) use (&$connection, $offset) 
		{
			return $connection->increment($params['key'], $offset);
		};
	}

	public function clear() 
	{
		return $this->connection->flush();
	}
	
	public static function enabled() 
	{
		return extension_loaded('memcached');
	}
}