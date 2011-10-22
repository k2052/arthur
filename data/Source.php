<?php

namespace arthur\data;

use arthur\core\NetworkException;

abstract class Source extends \arthur\core\Object 
{
	protected $_autoConfig = array('classes' => 'merge');

	protected $_classes = array(
		'entity'       => 'arthur\data\Entity',
		'set'          => 'arthur\data\Collection',
		'relationship' => 'arthur\data\model\Relationship'
	);

	public $connection = null;
	protected $_isConnected = false;

	public function __construct(array $config = array()) 
	{
		$defaults = array('autoConnect' => true);
		parent::__construct($config + $defaults);
	}

	public function __destruct() 
	{
		if($this->isConnected())
			$this->disconnect();
	}

	protected function _init() 
	{
		parent::_init();
		if($this->_config['autoConnect'])
			$this->connect();
	}
	
	public function isConnected(array $options = array()) 
	{
		$defaults = array('autoConnect' => false);
		$options += $defaults;

		if(!$this->_isConnected && $options['autoConnect']) 
		{
			try {
				$this->connect();
			} 
			catch (NetworkException $e) {
				$this->_isConnected = false;
			}
		}              
		
		return $this->_isConnected;
	}

	public function name($name) 
	{
		return $name;
	}
	
	abstract public function connect();
	abstract public function disconnect();
	abstract public function sources($class = null);
	abstract public function describe($entity, array $meta = array());
	abstract public function relationship($class, $type, $name, array $options = array());
	abstract public function create($query, array $options = array());
	abstract public function read($query, array $options = array());
	abstract public function update($query, array $options = array());
	abstract public function delete($query, array $options = array());

	public function cast($entity, array $data, array $options = array()) 
	{
		$defaults = array('first' => false);
		$options += $defaults;
		return $options['first'] ? reset($data) : $data;
	}

	public function methods() 
	{
		return get_class_methods($this);
	}

	public function configureClass($class) 
	{
		return array('meta' => array(
			'key'    => 'id',
			'locked' => true
		));
	}

	public function item($model, array $data = array(), array $options = array()) 
	{
		$defaults = array('class' => 'entity');
		$options += $defaults;

		$class = $options['class'];
		unset($options['class']); 
		
		return $this->_instance($class, compact('model', 'data') + $options);
	}
}