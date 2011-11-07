<?php

namespace arthur\analysis\logger\adapter;

class Syslog extends \arthur\core\Object 
{
	protected $_isConnected = false;

	protected $_priorities = array(
		'emergency' => LOG_EMERG,
		'alert'     => LOG_ALERT,
		'critical'  => LOG_CRIT,
		'error'     => LOG_ERR,
		'warning'   => LOG_WARNING,
		'notice'    => LOG_NOTICE,
		'info'      => LOG_INFO,
		'debug'     => LOG_DEBUG
	);

	public function __construct(array $config = array()) 
	{
		$defaults = array('identity' => false, 'options'  => LOG_ODELAY, 'facility' => LOG_USER);
		parent::__construct($config + $defaults);
	}

	public function write($priority, $message) 
	{
		$config = $this->_config;
		$_priorities = $this->_priorities;

		if(!$this->_isConnected) 
		{
			closelog();
			openlog($config['identity'], $config['options'], $config['facility']);
			$this->_isConnected = true;
		}

		return function($self, $params) use ($_priorities) 
		{
			$priority = $_priorities[$params['priority']];
			return syslog($priority, $params['message']);
		};
	}
}