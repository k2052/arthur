<?php

namespace arthur\data\source;

use arthur\util\String;

class Http extends \arthur\data\Source 
{
	public $connection = null;
	protected $_autoConfig = array('classes' => 'merge');
	
	protected $_classes = array(
		'service'      => 'arthur\net\http\Service',
		'relationship' => 'arthur\data\model\Relationship'
	);
             
	protected $_isConnected = false;
	
	protected $_methods = array(
		'read'	 => array('method' => 'get', 'path' => "/{:source}"),
		'create' => array('method' => 'post', 'path' => "/{:source}"),
		'update' => array('method' => 'put', 'path' => "/{:source}/{:id}"),
		'delete' => array('method' => 'delete', 'path' => "/{:source}/{:id}")
	);
     
	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'adapter'	   => null,
			'persistent' => false,
			'scheme'     => 'http',
			'host'       => 'localhost',
			'version'    => '1.1',
			'auth'       => null,
			'login'      => '',
			'password'   => '',
			'port'       => 80,
			'timeout'    => 30,
			'encoding'   => 'UTF-8'
		);
		
		$config = $config + $defaults;
		$config['username'] = $config['login'];
		
		parent::__construct($config);
	}

	protected function _init() 
	{
		$config = $this->_config;
		unset($config['type']);
		$this->connection = $this->_instance('service', $config);
		
		parent::_init();
	}
     
	public function __get($property) {
		return $this->connection->{$property};
	}

	public function __call($method, $params) 
	{
		if(isset($this->_config['methods'][$method])) 
		{
			$params += array(array(), array());
			$string = $this->_config['methods'][$method];

			if(!isset($string['path']))
				$string['path'] = $method;
				
			$conn   =& $this->connection;
			$filter = function($self, $params) use (&$conn, $string) 
			{
				$data = in_array($string['method'], array('post', 'put'))
					? (array) $params[0] : array();
				$path = String::insert($string['path'], $data, array('clean' => true));
				
				return $conn->{$string['method']}($path, $data, $params[1]);
			};
			
			return $this->_filter(__METHOD__, $params, $filter);
		} 
		
		return $this->connection->invokeMethod($method, $params);
	}
    
	public function connect() 
	{
		if(!$this->_isConnected)
			$this->_isConnected = true;
			
		return $this->_isConnected;
	}

	public function disconnect() 
	{
		if($this->_isConnected && $this->connection !== null)
			$this->_isConnected = false;                         
			
		return !$this->_isConnected;
	}

	public function sources($class = null) 
	{
		return array();
	}

	public function describe($entity, array $meta = array()) 
	{
		return array();
	}

	public function create($query, array $options = array()) 
	{
		$params = compact('query', 'options');
		$config = $this->_config;

		if(!isset($this->_methods[__FUNCTION__]))
			return null;
		
		$method = $this->_methods[__FUNCTION__];
		$filter = function($self, $params) use ($config, $method) 
		{
			$query   = $params['query'];
			$options = $params['options'];
			$data    = array();

			if($query) 
			{
				$options += array_filter($query->export($self), function($v) {
					return $v !== null;
				});
				$data = $query->data();
			}
			$path = String::insert($method['path'], $options, array('clean' => true));   
			
			return $self->connection->{$method['method']}($path, $data, $options);
		};
		return $this->_filter(__METHOD__, $params, $filter);
	}

	public function read($query, array $options = array()) 
	{
		$params = compact('query', 'options');
		$conn =& $this->connection;

		if(!isset($this->_methods[__FUNCTION__]))
			return null;

		$method = $this->_methods[__FUNCTION__];
		$filter = function($self, $params) use (&$conn, $method) 
		{
			$query    = $params['query'];
			$options  = $params['options'];
			$data     = array();
			$defaults = array('conditions' => null, 'limit' => null);

			if($query)
			{
				$options += array_filter($query->export($self), function($v) {
					return $v !== null;
				});  
				
				$options += $defaults;
				$data = (array) $options['conditions'] + (array) $options['limit'];
			}
			$path = String::insert($method['path'], $options, array('clean' => true));  
			
			return $conn->{$method['method']}($path, $data, $options);
		};     
		
		return $this->_filter(__METHOD__, $params, $filter);
	}

	public function update($query, array $options = array()) 
	{
		$params = compact('query', 'options');
		$conn   =& $this->connection;

		if(!isset($this->_methods[__FUNCTION__]))
			return null;

		$method = $this->_methods[__FUNCTION__];
		$filter = function($self, $params) use (&$conn, $method) 
		{
			$query   = $params['query'];
			$options = $params['options'];
			$data   = array();

			if($query) 
			{
				$options += array_filter($query->export($self), function($v) {
					return $v !== null;
				});
				$data = $query->data();
			}
			$path = String::insert($method['path'], $options + $data, array('clean' => true)); 
			
			return $conn->{$method['method']}($path, $data, $options);
		};    
		
		return $this->_filter(__METHOD__, $params, $filter);
	}


	public function delete($query, array $options = array()) 
	{
		$params = compact('query', 'options');
		$conn =& $this->connection;

		if(!isset($this->_methods[__FUNCTION__]))
			return null;

		$method = $this->_methods[__FUNCTION__];
		$filter = function($self, $params) use (&$conn, $method) 
		{
			$query   = $params['query'];
			$options = $params['options'];
			$data    = array();

			if($query) {
				$options += $query->export($self);
				$data = $query->data();
			}
			$path = String::insert($method['path'], $options + $data, array('clean' => true));
			
			return $conn->{$method['method']}($path, array(), $options);
		};   
		
		return $this->_filter(__METHOD__, $params, $filter);
	}


	public function relationship($class, $type, $name, array $options = array()) 
	{
		if(isset($this->_classes['relationship']))
			return $this->_instance('relationship', compact('type', 'name') + $options);

		return null;
	}

	public function name($name) 
	{
		return $name;
	}
}