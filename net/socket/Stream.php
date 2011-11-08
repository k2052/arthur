<?php

namespace arthur\net\socket;

use arthur\core\NetworkException;

class Stream extends \arthur\net\Socket 
{
	public function open(array $options = array()) 
	{
		parent::open($options);
		$config = $this->_config;

		if(!$config['scheme'] || !$config['host'])
			return false;

		$scheme = ($config['scheme'] !== 'udp') ? 'tcp' : 'udp';
		$port   = $config['port'] ?: 80;
		$host   = "{$scheme}://{$config['host']}:{$port}";
		$flags  = STREAM_CLIENT_CONNECT;

		if($config['persistent'])
			$flags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT;

		$this->_resource = stream_socket_client(
			$host, $errorCode, $errorMessage, $config['timeout'], $flags
		);
		if($errorCode || $errorMessage)
			throw new NetworkException($errorMessage);

		$this->timeout($config['timeout']);

		if(!empty($config['encoding']))
			$this->encoding($config['encoding']);

		return $this->_resource;
	}

	public function close() 
	{
		if(!is_resource($this->_resource))
			return true;

		fclose($this->_resource);

		if(is_resource($this->_resource))
			$this->close();

		return true;
	}

	public function eof() 
	{
		return is_resource($this->_resource) ? feof($this->_resource) : true;
	}

	public function read($length = null, $offset = null) 
	{
		if(!is_resource($this->_resource))
			return false;
		if(!$length)
			return stream_get_contents($this->_resource);

		return stream_get_contents($this->_resource, $length, $offset);
	}

	public function write($data = null) 
	{
		if(!is_resource($this->_resource))
			return false;
		if(!is_object($data))
			$data = $this->_instance($this->_classes['request'], (array) $data + $this->_config);

		return fwrite($this->_resource, (string) $data, strlen((string) $data));
	}

	public function timeout($time) 
	{
		if(!is_resource($this->_resource))
			return false;

		return stream_set_timeout($this->_resource, $time);
	}

	public function encoding($charset) 
	{
		if(!function_exists('stream_encoding'))
			return false;

		return is_resource($this->_resource) ? stream_encoding($this->_resource, $charset) : false;
	}
}