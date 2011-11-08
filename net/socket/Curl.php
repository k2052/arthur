<?php

namespace arthur\net\socket;

class Curl extends \arthur\net\Socket 
{
	public $options = array();

	public function __construct(array $config = array()) 
	{
		$defaults = array('ignoreExpect' => true);
		parent::__construct($config + $defaults);
	}

	public function open(array $options = array()) 
	{
		parent::open($options);
		$config = $this->_config;

		if(empty($config['scheme']) || empty($config['host']))
			return false;

		$url = "{$config['scheme']}://{$config['host']}";
		$this->_resource = curl_init($url);
		curl_setopt($this->_resource, CURLOPT_PORT, $config['port']);
		curl_setopt($this->_resource, CURLOPT_HEADER, true);
		curl_setopt($this->_resource, CURLOPT_RETURNTRANSFER, true);

		if(!is_resource($this->_resource))
			return false;

		$this->_isConnected = true;
		$this->timeout($config['timeout']);

		if(isset($config['encoding']))
			$this->encoding($config['encoding']);

		return $this->_resource;
	}

	public function close() 
	{
		if(!is_resource($this->_resource))
			return true;

		curl_close($this->_resource);

		if(is_resource($this->_resource))
			$this->close();

		return true;
	}

	public function eof() 
	{
		return null;
	}

	public function read() 
	{
		if(!is_resource($this->_resource))
			return false;

		return curl_exec($this->_resource);
	}

	public function write($data = null)
	{
		if(!is_resource($this->_resource))
			return false;
		if(!is_object($data))
			$data = $this->_instance($this->_classes['request'], (array) $data + $this->_config);

		$this->set(CURLOPT_URL, $data->to('url'));

		if(is_a($data, 'arthur\net\http\Message')) 
		{
			if(!empty($this->_config['ignoreExpect']))
				$data->headers('Expect', ' ');
			if(isset($data->headers))
				$this->set(CURLOPT_HTTPHEADER, $data->headers());
			if(isset($data->method) && $data->method == 'POST')
				$this->set(array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => $data->body()));
		}        
		
		return (boolean) curl_setopt_array($this->_resource, $this->options);
	}

	public function timeout($time) 
	{
		if(!is_resource($this->_resource)) 
			return false;

		return curl_setopt($this->_resource, CURLOPT_CONNECTTIMEOUT, $time);
	}

	public function encoding($charset) { }    
	
	public function set($flags, $value = null) 
	{
		if($value !== null)
			$flags = array($flags => $value);

		$this->options += $flags;
	}
}