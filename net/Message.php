<?php

namespace arthur\net;

use ReflectionClass;
use ReflectionProperty;

class Message extends \arthur\core\Object 
{
	public $scheme = 'tcp';
	public $host = 'localhost';
	public $port = null;
	public $path = null;
	public $username = null;
	public $password = null;
	public $body = array();

	/**
	 * Adds config values to the public properties when a new object is created.
	 *
	 * @param array $config Configuration options : default value
	 * - `scheme`: tcp
	 * - `host`: localhost
	 * - `port`: null
	 * - `username`: null
	 * - `password`: null
	 * - `path`: null
	 * - `body`: null
	 */
	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'scheme'   => 'tcp',
			'host'     => 'localhost',
			'port'     => null,
			'username' => null,
			'password' => null,
			'path'     => null,
			'body'     => null
		);
		$config += $defaults;

		foreach(array_filter($config) as $key => $value) {
			$this->{$key} = $value;
		}
		parent::__construct($config);
	}

	public function body($data = null, $options = array()) 
	{
		$default = array('buffer' => null);
		$options += $default;
		$this->body = array_merge((array) $this->body, (array) $data);
		$body = join("\r\n", $this->body);  
		
		return ($options['buffer']) ? str_split($body, $options['buffer']) : $body;
	}

	public function to($format, array $options = array()) 
	{
		switch($format) 
		{
			case 'array':
				$array = array();
				$class = new ReflectionClass(get_class($this));

				foreach($class->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
					$array[$prop->getName()] = $prop->getValue($this);
				}
				return $array;
			case 'url':
				$host = $this->host . ($this->port ? ":{$this->port}" : '');
				return "{$this->scheme}://{$host}{$this->path}";
			case 'context':
				$defaults = array('content' => $this->body(), 'ignore_errors' => true);
				return array($this->scheme => $options + $defaults);
			case 'string':
			default:
				return (string) $this;
		}
	}

	public function __toString() 
	{
		return (string) $this->body();
	}
}