<?php

namespace lithium\security\auth\adapter;

class Http extends \lithium\core\Object 
{
	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'method' => 'digest', 'realm' => basename(LITHIUM_APP_PATH), 'users' => array()
		);
		parent::__construct($config + $defaults);
	}

	public function check($request, array $options = array()) 
	{
		$method = "_{$this->_config['method']}";
		return $this->{$method}($request);
	}
	
	public function set($data, array $options = array()) 
	{
		return $data;
	}

	public function clear(array $options = array()) { }

	protected function _basic($request)
	{
		$users    = $this->_config['users'];
		$username = $request->env('PHP_AUTH_USER');
		$password = $request->env('PHP_AUTH_PW');

		if(!isset($users[$username]) || $users[$username] !== $password) {
			$this->_writeHeader("WWW-Authenticate: Basic realm=\"{$this->_config['realm']}\"");
			return;
		}    
		
		return compact('username', 'password');
	}

	protected function _digest($request) 
	{
		$realm = $this->_config['realm'];
		$data = array(
			'username' => null, 'nonce' => null, 'nc' => null,
			'cnonce'   => null, 'qop' => null, 'uri' => null,
			'response' => null
		);

		$result  = array_map(function ($string) use (&$data) 
		{
  		$parts = explode('=', trim($string), 2) + array('', '');
  		$data[$parts[0]] = trim($parts[1], '"');      
		}, explode(',', $request->env('PHP_AUTH_DIGEST')));

		$users    = $this->_config['users'];
		$password = !empty($users[$data['username']]) ? $users[$data['username']] : null;

		$user  = md5("{$data['username']}:{$realm}:{$password}");
		$nonce = "{$data['nonce']}:{$data['nc']}:{$data['cnonce']}:{$data['qop']}";
		$req   = md5($request->env('REQUEST_METHOD') . ':' . $data['uri']);
		$hash  = md5("{$user}:{$nonce}:{$req}");

		if(!$data['username'] || $hash !== $data['response']) 
		{
			$nonce  = uniqid();
			$opaque = md5($realm);

			$message  = "WWW-Authenticate: Digest realm=\"{$realm}\",qop=\"auth\",";
			$message .= "nonce=\"{$nonce}\",opaque=\"{$opaque}\"";
			$this->_writeHeader($message);            
			
			return;
		}  
		
		return array('username' => $data['username'], 'password' => $password);
	}

	protected function _writeHeader($string) 
	{
		header($string, true);
	}
}