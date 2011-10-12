<?php

namespace arthur\net\http;

use arthur\core\Libraries;
use arthur\core\ClassNotFoundException;

class Service extends \arthur\core\Object 
{
	public $connection = null;
	public $last = null;
	protected $_autoConfig = array('classes' => 'merge');
	protected $_isConnected = false;

	protected $_classes = array(
		'media'    => 'arthur\net\http\Media',
		'request'  => 'arthur\net\http\Request',
		'response' => 'arthur\net\http\Response'
	);

	public function __construct(array $config = array()) {
		$defaults = array(
			'persistent' => false,
			'scheme'     => 'http',
			'host'       => 'localhost',
			'port'       => null,
			'timeout'    => 30,
			'auth'       => null,
			'username'   => null,
			'password'   => null,
			'encoding'   => 'UTF-8',
			'socket'     => 'Context'
		);     
		
		parent::__construct($config + $defaults);
	}
	
	protected function _init() 
	{
		$config = array('classes' => $this->_classes) + $this->_config;

		try {
			$this->connection = Libraries::instance('socket', $config['socket'], $config);
		} catch(ClassNotFoundException $e) {
			$this->connection = null;
		}
	}

	public function __call($method, $params = array()) 
	{
		array_unshift($params, $method);  
		
		return $this->invokeMethod('send', $params);
	}

	public function head(array $options = array()) 
	{
		return $this->send(__FUNCTION__, null, array(), $options);
	}

	public function get($path = null, $data = array(), array $options = array()) 
	{
		return $this->send(__FUNCTION__, $path, $data, $options);
	}

	public function post($path = null, $data = array(), array $options = array()) 
	{
		return $this->send(__FUNCTION__, $path, $data, $options);
	}

	public function put($path = null, $data = array(), array $options = array()) 
	{
		return $this->send(__FUNCTION__, $path, $data, $options);
	}

	public function delete($path = null, $data = array(), array $options = array()) 
	{
		return $this->send(__FUNCTION__, $path, $data, $options);
	}

	public function send($method, $path = null, $data = array(), array $options = array()) 
	{
		$defaults = array('return' => 'body');
		$options += $defaults;
		$request = $this->_request($method, $path, $data, $options);
		$options += array('message' => $request);

		if(!$this->connection || !$this->connection->open($options)) 
			return;

		$response = $this->connection->send($request, $options);
		$this->connection->close();
		$this->last = (object) compact('request', 'response');    
		
		return ($options['return'] == 'body' && $response) ? $response->body() : $response;
	}

	protected function _request($method, $path, $data, $options) 
	{
		$defaults = array('type' => 'form');
		$options += $defaults + $this->_config;  
		
		$request  = $this->_instance('request', $options);            
		$request->path   = str_replace('//', '/', "{$request->path}{$path}");
		$request->method = $method = strtoupper($method);             
		
		$hasBody        = in_array($method, array('POST', 'PUT'));

		$media = $this->_classes['media'];
		$type  = null;

		if($data && in_array($options['type'], $media::types())) 
		{
			$type        = $media::type($options['type']);
			$contentType = (array) $type['content'];   
			
			$request->headers(array('Content-Type' => current($contentType)));
			$data = $hasBody && !is_string($data) ?
				Media::encode($options['type'], $data, $options) : $data;
		}
		$hasBody ? $request->body($data) : $request->query = $data;       
		
		return $request;
	}
}