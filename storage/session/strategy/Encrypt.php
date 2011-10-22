<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2011, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace arthur\storage\session\strategy;

use arthur\core\ConfigException;

class Encrypt extends \arthur\core\Object 
{
	protected static $_vector = null;

	public function __construct(array $config = array()) 
	{
		if(!static::enabled())
			throw new ConfigException("The Mcrypt extension is not installed or enabled.");
		if(!isset($config['secret']))
			throw new ConfigException("Encrypt strategy requires a secret key.");
		$defaults = array(
			'cipher' => MCRYPT_RIJNDAEL_256,
			'mode'   => MCRYPT_MODE_CBC
		);
		parent::__construct($config + $defaults);

		$cipher = $this->_config['cipher'];
		$mode   = $this->_config['mode'];
		$this->_config['vector'] = static::_vector($cipher, $mode);
	}

	public function read($data, array $options = array()) 
	{
		$class = $options['class'];

		$encrypted = $class::read(null, array('strategies' => false));
		$key       = isset($options['key']) ? $options['key'] : null;

		if(!isset($encrypted['__encrypted']) || !$encrypted['__encrypted'])
			return isset($encrypted[$key]) ? $encrypted[$key] : null;

		$current = $this->_decrypt($encrypted['__encrypted']);

		if($key)
			return isset($current[$key]) ? $current[$key] : null;
		else 
			return $current;
	}

	public function write($data, array $options = array()) 
	{
		$class = $options['class'];

		$futureData = $this->read(null, array('key' => null) + $options) ?: array();
		$futureData = array($options['key'] => $data) + $futureData;

		$payload = empty($futureData) ? null : $this->_encrypt($futureData);

		$class::write('__encrypted', $payload, array('strategies' => false) + $options);     
		
		return $data;
	}

	public function delete($data, array $options = array()) 
	{
		$class = $options['class'];

		$futureData = $this->read(null, array('key' => null) + $options) ?: array();
		unset($futureData[$options['key']]);

		$payload = empty($futureData) ? null : $this->_encrypt($futureData);

		$class::write('__encrypted', $payload, array('strategies' => false) + $options); 
		
		return $data;
	}

	public static function enabled() 
	{
		return extension_loaded('mcrypt');
	}
	protected function _encrypt($decrypted = array()) 
	{
		$cipher = $this->_config['cipher'];
		$secret = $this->_config['secret'];
		$mode   = $this->_config['mode'];
		$vector = $this->_config['vector'];

		$encrypted = mcrypt_encrypt($cipher, $secret, serialize($decrypted), $mode, $vector);
		$data = base64_encode($encrypted) . base64_encode($vector);

		return $data;
	}

	protected function _decrypt($encrypted) 
	{
		$cipher = $this->_config['cipher'];
		$secret = $this->_config['secret'];
		$mode   = $this->_config['mode'];
		$vector = $this->_config['vector'];

		$vectorSize = strlen(base64_encode(str_repeat(" ", static::_vectorSize($cipher, $mode))));
		$vector     = base64_decode(substr($encrypted, -$vectorSize));
		$data       = base64_decode(substr($encrypted, 0, -$vectorSize));

		$decrypted = mcrypt_decrypt($cipher, $secret, $data, $mode, $vector);
		$data      = unserialize(trim($decrypted));

		return $data;
	}

	protected static function _vector($cipher, $mode) 
	{
		if(static::$_vector)
			return static::$_vector;

		$size = static::_vectorSize($cipher, $mode);    
		
		return static::$_vector = mcrypt_create_iv($size, MCRYPT_DEV_URANDOM);
	}

	protected static function _vectorSize($cipher, $mode) 
	{
		return mcrypt_get_iv_size($cipher, $mode);
	}
}