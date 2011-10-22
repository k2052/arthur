<?php

namespace arthur\storage\cache\adapter;

use SplFileInfo;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use arthur\core\Libraries;

class File extends \arthur\core\Object 
{
	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'path'   => Libraries::get(true, 'resources') . '/tmp/cache',
			'prefix' => '',
			'expiry' => '+1 hour'
		);   
		
		parent::__construct($config + $defaults);
	}

	public function write($key, $data, $expiry = null) 
	{
		$path   = $this->_config['path'];
		$expiry = ($expiry) ?: $this->_config['expiry'];

		return function($self, $params) use (&$path, $expiry) 
		{
			$expiry = strtotime($expiry);
			$data   = "{:expiry:{$expiry}}\n{$params['data']}";
			$path   = "{$path}/{$params['key']}";
			return file_put_contents($path, $data);
		};
	}

	public function read($key) 
	{
		$path = $this->_config['path'];

		return function($self, $params) use (&$path) 
		{
			extract($params);
			$path = "$path/$key";
			$file = new SplFileInfo($path);

			if(!$file->isFile() || !$file->isReadable())
				return false;

			$data   = file_get_contents($path);
			preg_match('/^\{\:expiry\:(\d+)\}\\n/', $data, $matches);
			$expiry = $matches[1];

			if($expiry < time()) {
				unlink($path);
				return false;
			}    
			
			return preg_replace('/^\{\:expiry\:\d+\}\\n/', '', $data, 1);
		};
	}

	public function delete($key) 
	{
		$path = $this->_config['path'];

		return function($self, $params) use (&$path) 
		{
			extract($params);
			$path = "$path/$key";
			$file = new SplFileInfo($path);

			if($file->isFile() && $file->isReadable()) 
				return unlink($path);
			
			return false;
		};
	}

	public function increment($key, $offset = 1) 
	{
		return false;
	}
	
	public function decrement($key, $offset = 1) 
	{
		return false;
	}

	public function clear() 
	{
		$base     = new RecursiveDirectoryIterator($this->_config['path']);
		$iterator = new RecursiveIteratorIterator($base);

		foreach($iterator as $file) 
		{
			if($file->isFile())
				unlink($file->getPathName());
		}     
		
		return true;
	}

	public static function enabled() 
	{
		return true;
	}
}