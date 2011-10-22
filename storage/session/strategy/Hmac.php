<?php

namespace arthur\storage\session\strategy;

use RuntimeException;
use arthur\core\ConfigException;
use arthur\storage\session\strategy\MissingSignatureException;

class Hmac extends \arthur\core\Object 
{
	protected static $_secret = null;

	public function __construct(array $config = array()) 
	{
		if(!isset($config['secret']))
			throw new ConfigException("HMAC strategy requires a secret key.");

		static::$_secret = $config['secret'];
	}

	public function write($data, array $options = array()) 
	{
		$class = $options['class'];

		$futureData = $class::read(null, array('strategies' => false));
		$futureData = array($options['key'] => $data) + $futureData;
		unset($futureData['__signature']);

		$signature = static::_signature($futureData);
		$class::write('__signature', $signature, array('strategies' => false) + $options);  
		
		return $data;
	}

	public function read($data, array $options = array()) 
	{
		$class = $options['class'];

		$currentData = $class::read(null, array('strategies' => false));

		if(!isset($currentData['__signature']))
			throw new MissingSignatureException('HMAC signature not found.'); 
			
		$currentSignature = $currentData['__signature'];
		$signature        = static::_signature($currentData);

		if($signature !== $currentSignature) {
			$message = "Possible data tampering: HMAC signature does not match data.";
			throw new RuntimeException($message);
		}        
		
		return $data;
	}

	public function delete($data, array $options = array()) 
	{
		$class = $options['class'];

		$futureData = $class::read(null, array('strategies' => false));
		unset($futureData[$options['key']]);

		$signature = static::_signature($futureData);
		$class::write('__signature', $signature, array('strategies' => false) + $options);    
		
		return $data;
	}

	protected static function _signature($data, $secret = null) 
	{
		unset($data['__signature']);
		$secret = ($secret) ?: static::$_secret;       
		
		return hash_hmac('sha1', serialize($data), $secret);
	}
}