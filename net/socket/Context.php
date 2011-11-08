<?php

namespace arthur\net\socket;

class Context extends \arthur\net\Socket 
{
	protected $_timeout = 30;
	protected $_content = null;

	public function __construct(array $config = array()) 
	{
		$defaults = array('mode' => 'r', 'message' => null);
		parent::__construct($config + $defaults);
		$this->timeout($this->_config['timeout']);
	}

	public function open(array $options = array()) 
	{
		parent::open($options);
		$config = $this->_config;

		if(!$config['scheme'] || !$config['host'])
			return false;

		$url     = "{$config['scheme']}://{$config['host']}:{$config['port']}";
		$context = array($config['scheme'] => array('timeout' => $this->_timeout));

		if(is_object($config['message'])) {
			$url = $config['message']->to('url');
			$context = $config['message']->to('context', array('timeout' => $this->_timeout));
		}
		$this->_resource = fopen($url, $config['mode'], false, stream_context_create($context));
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
		if(!is_resource($this->_resource))
			return true;

		return feof($this->_resource);
	}

	public function read() 
	{
		if(!is_resource($this->_resource))
			return false;

		$meta    = stream_get_meta_data($this->_resource);
		$headers = isset($meta['wrapper_data'])
			? join("\r\n", $meta['wrapper_data']) . "\r\n\r\n" : null;
		return $headers . stream_get_contents($this->_resource);
	}

	public function write($data = null) 
	{
		if(!is_resource($this->_resource))
			return false;

		if(!is_object($data))
			$data = $this->_instance($this->_classes['request'], (array) $data + $this->_config);

		return stream_context_set_option(
			$this->_resource, $data->to('context', array('timeout' => $this->_timeout))
		);
	}

	public function timeout($time = null) 
	{
		if($time !== null)
			$this->_timeout = $time;

		return $this->_timeout;
	}

	public function encoding($charset = null) 
	{
		return false;
	}
}