<?php

namespace arthur\console;

class Request extends \arthur\core\Object 
{
	public $argv = array();

	public $params = array(
		'command' => null, 'action' => 'run', 'args' => array()
	);
	
	public $input;
	protected $_env = array();
	protected $_locale = null;
	protected $_autoConfig = array('env' => 'merge');

	public function __construct($config = array()) 
	{
		$defaults = array('args' => array(), 'input' => null);
		$config  += $defaults;
		parent::__construct($config);
	}

	protected function _init() 
	{
		$this->_env += (array) $_SERVER + (array) $_ENV;
		$this->_env['working'] = getcwd() ?: null;
		$argv = (array) $this->env('argv');
		$this->_env['script'] = array_shift($argv);
		$this->argv += $argv + (array) $this->_config['args'];
		$this->input = $this->_config['input'];

		if(!is_resource($this->_config['input']))
			$this->input = fopen('php://stdin', 'r');
		
		parent::_init();
	}

	public function __get($name) 
	{
		if(isset($this->params[$name]))
			return $this->params[$name];
	}

	public function __isset($name) 
	{
		return isset($this->params[$name]);
	}

	public function args($key = 0) 
	{
		if(!empty($this->args[$key])) 
			return $this->args[$key];
		
		return null;
	}

	public function env($key = null) 
	{
		if(!empty($this->_env[$key]))
			return $this->_env[$key];
		if($key === null)
			return $this->_env;
		
		return null;
	}

	public function shift($num = 1) 
	{
		for($i = $num; $i > 1; $i--) {
			$this->shift(--$i);
		}
		$this->params['command'] = $this->params['action'];   
		
		if(isset($this->params['args'][0]))
			$this->params['action'] = array_shift($this->params['args']);

		return $this;
	}
	public function input() 
	{
		return fgets($this->input);
	}

	public function locale($locale = null) 
	{
		if($locale)
			$this->_locale = $locale;

		return $this->_locale;
	}

	public function __destruct() 
	{
		if($this->input)
			fclose($this->input);
	}
}