<?php

namespace arthur\analysis\logger\adapter;

use arthur\util\String;
use arthur\core\Libraries;

class File extends \arthur\core\Object 
{
	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'path' => Libraries::get(true, 'resources') . '/tmp/logs',
			'timestamp' => 'Y-m-d H:i:s',
			'file' => function($data, $config) { return "{$data['priority']}.log"; },
			'format' => "{:timestamp} {:message}\n"
		);     
		
		parent::__construct($config + $defaults);
	}
	public function write($priority, $message) 
	{
		$config = $this->_config;

		return function($self, $params) use (&$config) 
		{
			$path = $config['path'] . '/' . $config['file']($params, $config);
			$params['timestamp'] = date($config['timestamp']);
			$message = String::insert($config['format'], $params); 
			
			return file_put_contents($path, $message, FILE_APPEND);
		};
	}
}