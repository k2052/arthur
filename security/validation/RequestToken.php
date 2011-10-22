<?php

namespace arthur\security\validation;

use arthur\security\Password;
use arthur\util\String;
use arthur\util\Set;

class RequestToken 
{
	protected static $_classes = array(
		'session' => 'arthur\storage\Session'
	);

	public static function config(array $config = array()) 
	{
		if(!$config) 
			return array('classes' => static::$_classes);

		foreach($config as $key => $val) 
		{
			$key = "_{$key}";

			if(isset(static::${$key}))
				static::${$key} = $val + static::${$key};
		}
	}

	public static function get(array $options = array()) 
	{
		$defaults = array(
			'regenerate' => false,
			'sessionKey' => 'security.token',
			'salt'       => null,
			'type'       => 'sha512'
		);
		$options += $defaults;
		$session  = static::$_classes['session'];

		if($options['regenerate'] || !($token = $session::read($options['sessionKey']))) {
			$token = String::hash(uniqid(microtime(true)), $options);
			$session::write($options['sessionKey'], $token);
		}  
		
		return $token;
	}

	public static function key(array $options = array()) 
	{
		return Password::hash(static::get($options));
	}

	public static function check($key, array $options = array()) 
	{
		$defaults = array('sessionKey' => 'security.token');
		$options += $defaults;
		$session  = static::$_classes['session'];

		if(is_object($key) && isset($key->data)) {
			$result = Set::extract($key->data, '/security/token');
			$key = $result ? $result[0] : null;
		}  
		
		return Password::check($session::read($options['sessionKey']), (string) $key);
	}
}