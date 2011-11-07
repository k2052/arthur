<?php

namespace arthur\analysis\logger\adapter;

use arthur\util\String;

class Cache extends \arthur\core\Object 
{
	protected $_classes = array(
		'cache' => '\arthur\storage\Cache'
	);

	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'config' => null,
			'expiry' => '+999 days',
			'key'    => 'log_{:type}_{:timestamp}'
		);
		parent::__construct($config + $defaults);
	}

	public function write($type, $message) 
	{
		$config = $this->_config + $this->_classes;

		return function($self, $params) use ($config) 
		{
			$params += array('timestamp' => strtotime('now'));
			$key     = $config['key'];
			$key     = is_callable($key) ? $key($params) : String::insert($key, $params);

			$cache = $config['cache'];
			return $cache::write($config['config'], $key, $params['message'], $config['expiry']);
		};
	}
}