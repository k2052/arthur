<?php

namespace arthur\data\model;

use arthur\data\Source;
use arthur\core\ConfigException;
use arthur\data\model\QueryException;

class Query extends \arthur\core\Object 
{
	protected $_type = null;
	protected $_map = array();
	protected $_entity = null;
	protected $_data = array();
	protected $_autoConfig = array('type', 'map');

	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'calculate'  => null,
			'conditions' => array(),
			'fields'     => array(),
			'data'       => array(),
			'model'      => null,
			'alias'      => null,
			'source'     => null,
			'order'      => null,
			'offset'     => null,
			'name'       => null,
			'limit'      => null,
			'page'       => null,
			'group'      => null,
			'comment'    => null,
			'joins'      => array(),
			'with'       => array(),
			'map'        => array(),
			'whitelist'  => array(),
			'relationships' => array()
		);        
		
		parent::__construct($config + $defaults);
	}

	protected function _init() 
	{
		parent::_init();
		unset($this->_config['type']);

		foreach($this->_config as $key => $val) 
		{
			if(method_exists($this, $key) && $val !== null) {
				$this->_config[$key] = is_array($this->_config[$key]) ? array() : null;
				$this->{$key}($val);
			}
		}
		if($list = $this->_config['whitelist'])
			$this->_config['whitelist'] = array_combine($list, $list);
		if($this->_config['with'])
			$this->_associate($this->_config['with']);  
			
		$joins                  = $this->_config['joins'];
		$this->_config['joins'] = array();

		foreach($joins as $i => $join) {
			$this->join($i, $join);
		}
		if($this->_entity && !$this->_config['model'])
			$this->model($this->_entity->model());    
			
		unset($this->_config['entity'], $this->_config['init'], $this->_config['with']);
	}

	public function type() 
	{
		return $this->_type;
	}
	
	public function map($map = null) 
	{
		if($map !== null) {
			$this->_map = $map;
			return $this;
		}    
		
		return $this->_map;
	}

	public function calculate($calculate = null) 
	{
		if($calculate) {
			$this->_config['calculate'] = $calculate;
			return $this;
		}  
		
		return $this->_config['calculate'];
	}

	public function model($model = null) 
	{
		if($model) 
		{
			$this->_config['model']  = $model;
			$this->_config['source'] = $this->_config['source'] ?: $model::meta('source');
			$this->_config['alias']  = $this->_config['alias'] ?: $model::meta('name');
			$this->_config['name']   = $this->_config['name'] ?: $this->_config['alias']; 
			
			return $this;
		}    
		
		return $this->_config['model'];
	}

	public function conditions($conditions = null) 
	{
		if($conditions)
		{
			$conditions                  = (array) $conditions;
			$this->_config['conditions'] = (array) $this->_config['conditions'];
			$this->_config['conditions'] = array_merge($this->_config['conditions'], $conditions);  
			
			return $this;
		}      
		
		return $this->_config['conditions'] ?: $this->_entityConditions();
	}

	public function fields($fields = null, $overwrite = false) 
	{
		if($fields === false || $overwrite)
			$this->_config['fields'] = array();
		$this->_config['fields'] = (array) $this->_config['fields'];

		if(is_array($fields))
			$this->_config['fields'] = array_merge($this->_config['fields'], $fields);
		elseif($fields && !isset($this->_config['fields'][$fields]))
			$this->_config['fields'][] = $fields;     
			
		if($fields !== null) return $this;    
		
		return $this->_config['fields'];
	}

	public function limit($limit = null) 
	{
		if($limit) {
			$this->_config['limit'] = intval($limit);
			return $this;
		}
		if($limit === false) {
			$this->_config['limit'] = null;
			return $this;
		}   
		
		return $this->_config['limit'];
	}

	public function offset($offset = null) 
	{
		if($offset !== null) {
			$this->_config['offset'] = intval($offset);
			return $this;
		}
		
		return $this->_config['offset'];
	}

	public function page($page = null) 
	{
		if($page) 
		{
			$this->_config['page'] = $page = (intval($page) ?: 1);
			$this->offset(($page - 1) * $this->_config['limit']);
			return $this;
		}  
		
		return $this->_config['page'];
	}

	public function order($order = null) 
	{
		if($order) {
			$this->_config['order'] = $order;
			return $this;
		}        
		
		return $this->_config['order'];
	}

	public function group($group = null) 
	{
		if($group) {
			$this->_config['group'] = $group;
			return $this;
		}
		if($group === false) {
			$this->_config['group'] = null;
			return $this;
		}  
		
		return $this->_config['group'];
	}

	public function comment($comment = null) 
	{
		if($comment) {
			$this->_config['comment'] = $comment;
			return $this;
		}   
		
		return $this->_config['comment'];
	}

	public function &entity(&$entity = null) 
	{
		if($entity) {
			$this->_entity = $entity;
			return $this;
		}
		return $this->_entity;
	}

	public function data($data = array()) 
	{
		$bind =& $this->_entity;

		if($data) {
			$bind ? $bind->set($data) : $this->_data = array_merge($this->_data, $data);
			return $this;
		}
		$data = $bind ? $bind->data() : $this->_data;  
		
		return ($list = $this->_config['whitelist']) ? array_intersect_key($data, $list) : $data;
	}

	public function join($name = null, $join = null) 
	{
		if(is_scalar($name) && !$join && isset($this->_config['joins'][$name]))
			return $this->_config['joins'][$name];
		if($name && !$join) {
			$join = $name;
			$name = null;
		}
		if($join) 
		{
			$join = is_array($join) ? $this->_instance(get_class($this), $join) : $join;
			$name ? $this->_config['joins'][$name] = $join : $this->_config['joins'][] = $join;
			return $this;
		}     
		
		return $this->_config['joins'];
	}

	public function export(Source $dataSource, array $options = array()) 
	{
		$defaults = array('keys' => array());
		$options += $defaults;

		$keys    = $options['keys'] ?: array_keys($this->_config);
		$methods = $dataSource->methods();
		$results = array('type' => $this->_type);

		$apply = array_intersect($keys, $methods);
		$copy  = array_diff($keys, $apply);

		foreach($apply as $item) {
			$results[$item] = $dataSource->{$item}($this->{$item}(), $this);
		}
		foreach($copy as $item) {
			if(in_array($item, $keys))
				$results[$item] = $this->_config[$item];
		} 
		
		if(in_array('data', $keys)) 
			$results['data'] = $this->_exportData();
		if(isset($results['source']))
			$results['source'] = $dataSource->name($results['source']);

		if(!isset($results['fields'])) return $results;

		$created = array('fields', 'values');

		if(is_array($results['fields']) && array_keys($results['fields']) == $created)
			$results = $results['fields'] + $results;

		return $results;
	}

	protected function _exportData() 
	{
		$data = $this->_entity ? $this->_entity->export() : $this->_data;

		if(!$list = $this->_config['whitelist'])
			return $data;
		$list = array_combine($list, $list);

		if(!$this->_entity)
			return array_intersect_key($data, $list);     
			
		foreach($data as $type => $values) {
			if(!is_array($values)) continue;
			$data[$type] = array_intersect_key($values, $list);
		}          
		
		return $data;
	}

	public function schema($field = null) 
	{
		if(is_array($field)) {
			$this->_config['schema'] = $field;
			return $this;
		}

		if(isset($this->_config['schema'])) 
		{
			$schema = $this->_config['schema'];

			if($field) 
  			return isset($schema[$field]) ? $schema[$field] : null;

			return $schema;
		}

		if($model = $this->model())
			return $model::schema($field);
	}

	public function alias($alias = null) 
	{
		if($alias) {
			$this->_config['alias'] = $alias;
			return $this;
		}
		if(!$this->_config['alias'] && ($model = $this->_config['model']))
			$this->_config['alias'] = $model::meta('name');

		return $this->_config['alias'];
	}

	public function __call($method, array $params = array()) 
	{
		if($params) {
			$this->_config[$method] = current($params);
			return $this;
		} 
		
		return isset($this->_config[$method]) ? $this->_config[$method] : null;
	}

	protected function _entityConditions() 
	{
		if(!$this->_entity || !($model = $this->_config['model']))
			return;
		$key = $model::key($this->_entity->data());

		if(!$key && $this->_type != "create")
			throw new ConfigException('No matching primary key found.');
		if(is_array($key))
			return $key;

		$key = $model::meta('key');
		$val = $this->_entity->{$key};  
		
		return $val ? array($key => $val) : array();
	}

	protected function _associate($related) 
	{
		if(!$model = $this->model())
			return;

		foreach((array) $related as $name => $config) 
		{
			if(is_int($name)) $name = $config;
			if(!$relationship = $model::relations($name))
				throw new QueryException("Model relationship `{$name}` not found.");

			list($name, $query) = $this->_fromRelationship($relationship);
			$this->join($name, $query);
		}
	}

	protected function _fromRelationship($rel) 
	{
		$model     = $rel->to();
		$name      = $rel->name();
		$type      = $rel->type();
		$fieldName = $rel->fieldName();
		$this->_config['relationships'][$name] = compact('type', 'model', 'fieldName');

		$constraint = $rel->constraints();
		$class      = get_class($this);

		return array($name, $this->_instance($class, compact('constraint', 'model') + array(
			'type'  => 'LEFT',
			'alias' => $rel->name()
		)));
	}
}