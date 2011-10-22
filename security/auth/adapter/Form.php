<?php

namespace arthur\security\auth\adapter;

use arthur\core\Libraries;
use UnexpectedValueException;
use arthur\security\Password;

class Form extends \arthur\core\Object 
{
	protected $_model = '';
	protected $_fields = array();
	protected $_scope = array();
	protected $_filters = array();
	protected $_validators = array();
	protected $_query = 'first';

	protected $_autoConfig = array('model', 'fields', 'scope', 'filters', 'validators', 'query');

	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'model'      => 'Users',
			'query'      => 'first',
			'filters'    => array(),
			'validators' => array(),
			'fields'     => array('username', 'password')
		);
		$config += $defaults;

		$password = function($form, $data) 
		{
			return Password::check($form, $data);
		};
		$config['validators'] = array_filter($config['validators'] + compact('password'));

		parent::__construct($config + $defaults);
	}

	protected function _init() 
	{
		parent::_init();

		foreach($this->_fields as $key => $val) 
		{
			if(is_int($key)) {
				unset($this->_fields[$key]);
				$this->_fields[$val] = $val;
			}
		}      
		
		$this->_model = Libraries::locate('models', $this->_model);
	}

	public function check($credentials, array $options = array()) 
	{
		$model = $this->_model;
		$query = $this->_query;
		$data  = $this->_filters($credentials->data);

		$conditions = $this->_scope + array_diff_key($data, $this->_validators);
		$user       = $model::$query(compact('conditions') + $options);

		if(!$user) return false;   
		
		return $this->_validate($user, $data);
	}

	public function set($data, array $options = array()) 
	{
		return $data;
	}

	public function clear(array $options = array()) 
	{
	}     
	
	protected function _filters($data) 
	{
		$result = array();

		foreach($this->_fields as $from => $to) 
		{
			$result[$to] = isset($data[$from]) ? $data[$from] : null;

			if(!isset($this->_filters[$from])) {
				$result[$to] = !is_scalar($result[$to]) ? strval($result[$to]) : $result[$to];
				continue;
			}
			if($this->_filters[$from] === false)
				continue;

			if(!is_callable($this->_filters[$from])) {
				$message = "Authentication filter for `{$from}` is not callable.";
				throw new UnexpectedValueException($message);
			}
			$result[$to] = call_user_func($this->_filters[$from], $result[$to]);
		}   
		
		if(!isset($this->_filters[0]))
			return $result;
		if(!is_callable($this->_filters[0]))
			throw new UnexpectedValueException("Authentication filter is not callable.");

		return call_user_func($this->_filters[0], $result);
	}

	protected function _validate($user, array $data) 
	{
		foreach($this->_validators as $field => $validator) 
		{
			if(!isset($this->_fields[$field]) || $field === 0)
				continue;

			if(!is_callable($validator)) {
				$message = "Authentication validator for `{$field}` is not callable.";
				throw new UnexpectedValueException($message);
			}

			$field = $this->_fields[$field];
			$value = isset($data[$field]) ? $data[$field] : null;

			if(!call_user_func($validator, $value, $user->data($field)))
				return false;
		}    
		
		$user = $user->data();

		if(!isset($this->_validators[0]))
			return $user;
		if(!is_callable($this->_validators[0]))
			throw new UnexpectedValueException("Authentication validator is not callable.");

		return call_user_func($this->_validators[0], $data, $user) ? $user : false;
	}
}