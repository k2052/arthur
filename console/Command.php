<?php

namespace arthur\console;

use Exception;
use arthur\core\Environment;
use arthur\console\command\Help;

class Command extends \arthur\core\Object 
{
	public $help = false;
	public $request;
	public $response;

	protected $_classes = array(
		'response' => 'arthur\console\Response'
	);

	protected $_autoConfig = array('classes' => 'merge');

	public function __construct(array $config = array()) 
	{
		$defaults = array('request' => null, 'response' => array(), 'classes' => $this->_classes);
		parent::__construct($config + $defaults);
	}

	protected function _init()
	{
		parent::_init(); 
		
		$this->request  = $this->_config['request'];
		$resp           = $this->_config['response'];
		$this->response = is_object($resp) ? $resp : $this->_instance('response', $resp);

		if(!is_object($this->request) || !$this->request->params)
			return;

		$default = array('command' => null, 'action' => null, 'args' => null);
		$params  = array_diff_key((array) $this->request->params, $default);

		foreach($params as $key => $param) {
			$this->{$key} = $param;
		}
	}

	public function __invoke($action, $args = array(), $options = array()) 
	{
		try 
		{
			if(!$this->request->env) 
			{
				$message  = 'Arthur console started in the ' . Environment::get() .' environment.';
				$message .= ' Use the --env=environment key to alter this.';
				$this->out($message);
			}
			$this->response->status = 1;
			$result = $this->invokeMethod($action, $args);

			if(is_int($result))
				$this->response->status = $result;
			elseif($result || $result === null) 
				$this->response->status = 0;
		} 
		catch(Exception $e) {
			$this->error($e->getMessage());
		}   
		
		return $this->response;
	}

	public function run() 
	{
		return $this->_help();
	}

	public function out($output = null, $options = array('nl' => 1)) 
	{
		$options = is_int($options) ? array('nl' => $options) : $options;
		return $this->_response('output', $output, $options);
	}

	public function error($error = null, $options = array('nl' => 1)) 
	{
		return $this->_response('error', $error, $options);
	}

	public function in($prompt = null, array $options = array())
	{
		$defaults = array('choices' => null, 'default' => null, 'quit' => 'q');
		$options += $defaults;
		$choices  = null;

		if(is_array($options['choices']))
			$choices = '(' . implode('/', $options['choices']) . ')';
		$default = $options['default'] ? "[{$options['default']}] " : '';

		do {
			$this->out("{$prompt} {$choices} \n {$default}> ", false);
			$result = trim($this->request->input());
		} 
		while (
			!empty($options['choices']) && !in_array($result, $options['choices'], true)
			&& (empty($options['quit']) || $result !== $options['quit'])
			&& ($options['default'] == null || $result !== '')
		);

		if($result == $options['quit'])
			return false;

		if($options['default'] !== null && $result == '')
			return $options['default'];

		return $result;
	}

	public function header($text, $line = 80) 
	{
		$this->hr($line);
		$this->out($text, 1, 'heading');
		$this->hr($line);
	}

	public function columns($rows, $options = array()) 
	{
		$defaults = array('separator' => "\t");
		$config = $options + $defaults;        
		
		$lengths = array_reduce($rows, function($columns, $row) 
		{
			foreach((array) $row as $key => $val) {
				if(!isset($columns[$key]) || strlen($val) > $columns[$key]) 
					$columns[$key] = strlen($val);
			}   
			
			return $columns;
		});   
		
		$rows = array_reduce($rows, function($rows, $row) use ($lengths, $config) 
		{
			$text = '';
			foreach((array) $row as $key => $val) {
				$text = $text . str_pad($val, $lengths[$key]) . $config['separator'];
			}
			$rows[] = $text;   
			
			return $rows;
		});   
		
		$this->out($rows, $config);
	}

	public function nl($number = 1) 
	{
		return str_repeat("\n", $number);
	}

	public function hr($length = 80, $newlines = 1) 
	{
		return $this->out(str_repeat('-', $length), $newlines);
	}

	public function clear() 
	{
		passthru(strtoupper(substr(PHP_OS, 0, 3)) == 'WIN' ? 'cls' : 'clear');
	}

	public function stop($status = 0, $message = null) 
	{
		if($message)
			($status == 0) ? $this->out($message) : $this->error($message);
		
		exit($status);
	}

	protected function _help() 
	{
		$help = new Help(array(
			'request'  => $this->request,
			'response' => $this->response,
			'classes'  => $this->_classes
		));       
		
		return $help->run(get_class($this));
	}

	protected function _response($type, $string, $options) 
	{
		$defaults = array('nl' => 1, 'style' => null);

		if(!is_array($options)) 
		{
			if(!$options || is_int($options))
				$options = array('nl' => $options);
			else if(is_string($options))
				$options = array('style' => $options);
			else
				$options = array();
		}
		$options += $defaults;

		if(is_array($string)) 
		{
			$method = ($type == 'error' ? $type : 'out');
			foreach($string as $out) {
				$this->{$method}($out, $options);
			}    
			
			return;
		}
		extract($options);

		if($style !== null)
			$string = "{:{$style}}{$string}{:end}";
		if($nl)
			$string = $string . $this->nl($nl);

		return $this->response->{$type}($string);
	}
}