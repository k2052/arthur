<?php

namespace arthur\action;

use BadMethodCallException;

class Response extends \arthur\net\http\Response 
{
	protected $_classes = array(
		'router' => 'arthur\net\http\Router',
		'media'  => 'arthur\net\http\Media'
	);

	protected $_autoConfig = array('classes' => 'merge');

	public function __construct(array $config = array()) 
	{
		$defaults = array('buffer' => 8192, 'location' => null, 'status' => 0, 'request' => null);
		parent::__construct($config + $defaults);
	}

	protected function _init() 
	{
		parent::_init();
		$config = $this->_config;
		$this->status($config['status']);
		unset($this->_config['status']);

		if($config['location']) 
		{
			$classes = $this->_classes;
			$location = $classes['router']::match($config['location'], $config['request']);
			$this->headers('location', $location);
		}
	}

	public function disableCache() 
	{
		$message = '`Request::disableCache()` is deprecated. Please use `Request::cache(false)`.';
		throw new BadMethodCallException($message);
	}

	public function cache($expires) 
	{
		if($expires === false) 
		{
			return $this->headers(array(
				'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
				'Cache-Control' => array(
					'no-store, no-cache, must-revalidate',
					'post-check=0, pre-check=0',
					'max-age=0'
				),
				'Pragma' => 'no-cache'
			));
		}
		$expires = is_int($expires) ? $expires : strtotime($expires);

		return $this->headers(array(
			'Expires' => gmdate('D, d M Y H:i:s', $expires) . ' GMT',
			'Cache-Control' => 'max-age=' . ($expires - time()),
			'Pragma' => 'cache'
		));
	}

	public function render() 
	{
		$code = null;

		if(isset($this->headers['location']) && $this->status['code'] === 200)
			$code = 302;

		$this->_writeHeader($this->status($code) ?: $this->status(500));

		foreach($this->headers as $name => $value) 
		{
			$key = strtolower($name);

			if($key == 'location')
				$this->_writeHeader("Location: {$value}", $this->status['code']);
			elseif($key == 'download')
				$this->_writeHeader('Content-Disposition: attachment; filename="' . $value . '"');
			elseif(is_array($value)) 
			{
				$this->_writeHeader(
					array_map(function($v) use ($name) { return "{$name}: {$v}"; }, $value)
				);
			}
			elseif(!is_numeric($name)) 
				$this->_writeHeader("{$name}: {$value}");
		}
		if($code == 302 || $code == 204)
			return;

		$chunked = $this->body(null, $this->_config);

		foreach($chunked as $chunk) {
			echo $chunk;
		}
	}

	public function __toString() 
	{
		$this->render();
		return '';
	}

	protected function _writeHeader($header, $code = null) 
	{
		if(is_array($header)) {
			array_map(function($h) { header($h, false); }, $header);
			return;
		}    
		
		$code ? header($header, true) : header($header, true, $code);
	}
}