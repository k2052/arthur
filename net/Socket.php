<?php

namespace arthur\net;

abstract class Socket extends \arthur\core\Object 
{
	protected $_resource = null;

	protected $_classes = array(
		'request'  => 'arthur\net\Message',
		'response' => 'arthur\net\Message'
	);

	protected $_autoConfig = array('classes' => 'merge');

	public function __construct(array $config = array()) {
		$defaults = array(
			'persistent' => false,
			'scheme'     => 'tcp',
			'host'       => 'localhost',
			'port'       => 80,
			'timeout'    => 30
		);
		parent::__construct($config + $defaults);
	}

	public function open(array $options = array()) 
	{
		parent::__construct($options + $this->_config);
		return false;
	}

	abstract public function close();
	abstract public function eof();
	abstract public function read();
	abstract public function write($data);
	abstract public function timeout($time);
	abstract public function encoding($charset);
	public function set($flags, $value = null) { }

	public function send($message = null, array $options = array()) 
	{
		$defaults = array('response' => $this->_classes['response']);
		$options += $defaults;

		if($this->write($message)) {
			$config = array('message' => $this->read()) + $this->_config;
			return $this->_instance($options['response'], $config);
		}
	}
	
	public function __destruct() 
	{
		$this->close();
	}

	public function resource() 
	{
		return $this->_resource;
	}
}