<?php

namespace arthur\template\helper;

class Security extends \arthur\template\Helper 
{

	protected $_classes = array(
		'requestToken' => 'arthur\security\validation\RequestToken'
	);

	public function __construct(array $config = array()) 
	{
		$defaults = array('sessionKey' => 'security.token', 'salt' => null);
		parent::__construct($config + $defaults);
	}

	public function requestToken(array $options = array()) 
	{
		$defaults     = array('name' => 'security.token', 'id' => false);
		$options     += $defaults;
		$requestToken = $this->_classes['requestToken'];

		$flags = array_intersect_key($this->_config, array('sessionKey' => '', 'salt' => ''));
		$value = $requestToken::key($flags);

		$name = $options['name'];
		unset($options['name']);      
		
		return $this->_context->form->hidden($name, compact('value') + $options);
	}
}