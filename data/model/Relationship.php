<?php

namespace arthur\data\model;

use arthur\core\Libraries;
use arthur\util\Inflector;
use arthur\core\ClassNotFoundException;

class Relationship extends \arthur\core\Object 
{
	const LINK_EMBEDDED = 'embedded';
	const LINK_CONTAINED = 'contained';
	const LINK_KEY = 'key';
	const LINK_KEY_LIST = 'keylist';
	const LINK_REF = 'ref';

	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'name'       => null,
			'key'        => array(),
			'type'       => null,
			'to'         => null,
			'from'       => null,
			'link'       => static::LINK_KEY,
			'fields'     => true,
			'fieldName'  => null,
			'constraint' => array()
		);  
		
		parent::__construct($config + $defaults);
	}

	protected function _init() {
	  
		parent::_init();
		$config =& $this->_config;
		$type   = $config['type'];

		$name                = ($type == 'hasOne') ? Inflector::pluralize($config['name']) : $config['name'];
		$config['fieldName'] = $config['fieldName'] ?: lcfirst($name);

		if(!$config['to']) {
			$assoc = preg_replace("/\\w+$/", "", $config['from']) . $name;
			$config['to'] = Libraries::locate('models', $assoc);
		}
		if(!$config['key'] || !is_array($config['key'])) 
			$config['key'] = $this->_keys($config['key']);
	}

	public function data($key = null) 
	{
		if(!$key) return $this->_config;

		return isset($this->_config[$key]) ? $this->_config[$key] : null;
	}

	public function constraints() 
	{
		$constraints = array();
		$config      = $this->_config;
		$relFrom     = $config['from']::meta('name');
		$relTo       = $config['name'];

		foreach($this->_config['key'] as $from => $to) {
			$constraints["{$relFrom}.{$from}"] = "{$relTo}.{$to}";
		}            
		
		return $constraints + (array) $this->_config['constraint'];
	}

	public function __call($name, $args = array()) 
	{
		return $this->data($name);
	}

	protected function _keys($keys) 
	{
		$config = $this->_config;

		if(!($related = ($config['type'] == 'belongsTo') ? $config['to'] : $config['from']))
			return array();
		if(class_exists($related))
			return array_combine((array) $keys, (array) $related::key());

		throw new ClassNotFoundException("Related model class '{$related}' not found.");
	}
}