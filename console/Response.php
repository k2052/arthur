<?php

namespace arthur\console;

use arthur\util\String;

class Response extends \arthur\core\Object 
{
	public $output = null;
	public $error = null;
	public $status = 0;

	public function __construct($config = array()) 
	{
		$defaults = array('output' => null, 'error' => null);
		$config += $defaults;

		$this->output = $config['output'];

		if(!is_resource($this->output))
			$this->output = fopen('php://stdout', 'r');

		$this->error = $config['error'];

		if(!is_resource($this->error))
			$this->error = fopen('php://stderr', 'r');

		parent::__construct($config);
	}

	public function output($output) 
	{
		return fwrite($this->output, String::insert($output, $this->styles()));
	}

	public function error($error) 
	{
		return fwrite($this->error, String::insert($error, $this->styles()));
	}

	public function __destruct()
	{
		if($this->output)
			fclose($this->output);
		if($this->error)
			fclose($this->error);
	}

	public function styles($styles = array())
	{
		$defaults = array(
			'heading' => "\033[1;36m",
			'option'  => "\033[0;35m",
			'command' => "\033[0;35m",
			'error'   => "\033[0;31m",
			'success' => "\033[0;32m",
			'black'   => "\033[0;30m",
			'red'     => "\033[0;31m",
			'green'   => "\033[0;32m",
			'yellow'  => "\033[0;33m",
			'blue'    => "\033[0;34m",
			'purple'  => "\033[0;35m",
			'cyan'    => "\033[0;36m",
			'white'   => "\033[0;37m",
			'end'     => "\033[0m"
		);
		if($styles === false) 
			return array_combine(array_keys($defaults), array_pad(array(), count($defaults), null));
		$styles += $defaults;

		if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
			return $this->styles(false);
		
		return $styles;
	}
}