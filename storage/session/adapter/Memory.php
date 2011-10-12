<?php

namespace arthur\storage\session\adapter;

use arthur\util\String;

class Memory extends \lithium\core\Object 
{
	public $_session = array();

	public static function key() 
	{
		return String::uuid();
	}
	
	public function isStarted() 
	{
		return true;
	}

	public function check($key, array $options = array()) 
	{
		$session =& $this->_session;   
		
		return function($self, $params) use (&$session) 
		{
			return isset($session[$params['key']]);
		};
	}

	public function read($key = null, array $options = array()) 
	{
		$session = $this->_session;

		return function($self, $params) use ($session) 
		{
			extract($params);

			if(!$key) return $session;          
			
			return isset($session[$key]) ? $session[$key] : null;
		};
	}

	public function write($key, $value, array $options = array()) 
	{
		$session =& $this->_session;

		return function($self, $params) use (&$session) 
		{
			extract($params);
			return (boolean) ($session[$key] = $value);
		};
	}

	public function delete($key, array $options = array()) 
	{
		$session =& $this->_session;

		return function($self, $params) use (&$session) 
		{
			extract($params);
			unset($session[$key]); 
			
			return !isset($session[$key]);
		};
	}

	public function clear(array $options = array()) 
	{
		$session =& $this->_session;

		return function($self, $params) use (&$session) 
		{
			$session = array();
		};
	}

	public static function enabled() 
	{
		return true;
	}
}