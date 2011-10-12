<?php

namespace arthur\net\http;

class Message extends \lithium\net\Message 
{
	public $protocol = null;
	public $version = '1.1';
	public $headers = array();
	protected $_type = 'html';
	protected $_classes = array('media' => 'lithium\net\http\Media');

	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'scheme'   => 'http',
			'host'     => 'localhost',
			'port'     => null,
			'username' => null,
			'password' => null,
			'path'     => null,
			'protocol' => null,
			'version'  => '1.1',
			'headers'  => array(),
			'body'     => null
		);
		$config += $defaults;
		parent::__construct($config);

		if(strpos($this->host, '/') !== false)
			list($this->host, $this->path) = explode('/', $this->host, 2);

		$this->path = str_replace('//', '/', "/{$this->path}/");
		$this->protocol = $this->protocol ?: "HTTP/{$this->version}";
	}

	public function headers($key = null, $value = null) 
	{
		if(is_string($key) && strpos($key, ':') === false) 
		{
			if($value === null) 
				return isset($this->headers[$key]) ? $this->headers[$key] : null;
			if($value === false) {
				unset($this->headers[$key]);
				return $this->headers;
			}
		}

		if($value)
			$this->headers = array_merge($this->headers, array($key => $value));
		else 
		{
			foreach((array) $key as $header => $value) 
			{
				if(!is_string($header)) 
				{
					if(preg_match('/(.*?):(.+)/i', $value, $match))
						$this->headers[$match[1]] = trim($match[2]);
				} 
				else
					$this->headers[$header] = $value;
			}
		}
		$headers = array();

		foreach($this->headers as $key => $value) {
			$headers[] = "{$key}: {$value}";
		}    
		
		return $headers;
	}

	public function type($type = null) 
	{
		if($type == null && $type !== false) 
			return $this->_type;

		if(strpos($type, '/')) 
		{
			$media = $this->_classes['media'];

			if(!$data = $media::type($type))
				return $this->_type;
			$type = is_array($data) ? reset($data) : $data;
		}      
		
		return $this->_type = $type;
	}
}