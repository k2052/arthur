<?php

namespace arthur\data;

use arthur\util\Set;
use arthur\util\Inflector;
use arthur\core\ConfigException;
use BadMethodCallException;

class Model extends \arthur\core\StaticObject 
{
	public $validates = array();
	public $hasOne = array();
	public $hasMany = array();
	public $belongsTo = array();
	protected static $_instances = array();
	protected $_instanceFilters = array();

	protected static $_classes = array(
		'connections' => 'arthur\data\Connections',
		'query'       => 'arthur\data\model\Query',
		'validator'   => 'arthur\util\Validator'
	);

	protected $_relations = array();
	protected $_relationTypes = array(
		'belongsTo' => array('class', 'key', 'conditions', 'fields'),
		'hasOne'    => array('class', 'key', 'conditions', 'fields'),
		'hasMany'   => array(
			'class', 'key', 'conditions', 'fields', 'order', 'limit'
		)
	);

	protected $_meta = array(
		'name'        => null,
		'title'       => null,
		'class'       => null,
		'source'      => null,
		'connection'  => 'default',
		'initialized' => false
	);
	
	protected $_schema = array();   

	protected $_query = array(
		'conditions' => null,
		'fields'     => null,
		'order'      => null,
		'limit'      => null,
		'page'       => null,
		'with'       => array()
	);   
	
	protected $_finders = array();
	protected static $_baseClasses = array(__CLASS__ => true);

	public static function __init() 
	{
		static::config();
	}

	public static function config(array $options = array()) 
	{
		if(static::_isBase($class = get_called_class()))
			return;

		$self    = static::_object();
		$query   = array();
		$meta    = array();
		$schema  = array();
		$source  = array();
		$classes = static::$_classes;

		foreach(static::_parents() as $parent) 
		{
			$parentConfig = get_class_vars($parent);

			foreach(array('meta', 'schema', 'classes', 'query') as $key) {
				if(isset($parentConfig["_{$key}"]))
					${$key} += $parentConfig["_{$key}"];
			}   
			
			if($parent == __CLASS__) break;
		}
		$tmp    = $options + $self->_meta + $meta;
		$source = array('meta' => array(), 'finders' => array(), 'schema' => array());

		if($tmp['connection']) {
			$conn = $classes['connections']::get($tmp['connection']);
			$source = (($conn) ? $conn->configureClass($class) : array()) + $source;
		}
		static::$_classes = $classes;
		$name = static::_name();

		$local                      = compact('class', 'name') + $options + $self->_meta;
		$self->_meta                = ($local + $source['meta'] + $meta);
		$self->_meta['initialized'] = false;
		$self->_schema             += $schema + $source['schema'];

		$self->_finders += $source['finders'] + $self->_findFilters();
		static::_relations();
	}

	public static function __callStatic($method, $params) 
	{
		$self     = static::_object();
		$isFinder = isset($self->_finders[$method]);

		if($isFinder && count($params) === 2 && is_array($params[1]))
			$params = array($params[1] + array($method => $params[0]));

		if($method == 'all' || $isFinder) 
		{
			if($params && is_scalar($params[0])) {
				$params[0] = array('conditions' => array($self->_meta['key'] => $params[0]));   
				
			return $self::find($method, $params ? $params[0] : array());
		}
		preg_match('/^findBy(?P<field>\w+)$|^find(?P<type>\w+)By(?P<fields>\w+)$/', $method, $args);

		if(!$args) {
			$message = "Method `%s` not defined or handled in class `%s`.";
			throw new BadMethodCallException(sprintf($message, $method, get_class($self)));
		}     
		
		$field   = Inflector::underscore($args['field'] ? $args['field'] : $args['fields']);
		$type    = isset($args['type']) ? $args['type'] : 'first';
		$type[0] = strtolower($type[0]);

		$conditions = array($field => array_shift($params));
		$params     = (isset($params[0]) && count($params) == 1) ? $params[0] : $params;    
		
		return $self::find($type, compact('conditions') + $params);
	}

	public static function find($type, array $options = array()) 
	{
		$self = static::_object();
		$finder = array();

		if($type === null) return null;

		if($type != 'all' && is_scalar($type) && !isset($self->_finders[$type])) {
			$options['conditions'] = array($self->_meta['key'] => $type);
			$type = 'first';
		}

		if(isset($self->_finders[$type]) && is_array($self->_finders[$type]))
			$options = Set::merge($self->_finders[$type], $options);

		$options = (array) $options + (array) $self->_query;
		$meta    = array('meta' => $self->_meta, 'name' => get_called_class());
		$params  = compact('type', 'options');

		$filter = function($self, $params) use ($meta) 
		{
			$options = $params['options'] + array('type' => 'read', 'model' => $meta['name']);
			$query   = $self::invokeMethod('_instance', array('query', $options));   
			
			return $self::connection()->read($query, $options);
		};  
		
		if(is_string($type) && isset($self->_finders[$type])) 
			$finder = is_callable($self->_finders[$type]) ? array($self->_finders[$type]) : array(); 
			
		return static::_filter(__FUNCTION__, $params, $filter, $finder);
	}

	public static function finder($name, $options = null) 
	{
		$self = static::_object();

		if(empty($options))
			return isset($self->_finders[$name]) ? $self->_finders[$name] : null;

		$self->_finders[$name] = $options;
	}

	public static function meta($key = null, $value = null) 
	{
		$self = static::_object();

		if($value)
			$self->_meta[$key] = $value;
		if(is_array($key))
			$self->_meta = $key + $self->_meta;

		if(!$self->_meta['initialized']) 
		{
			$self->_meta['initialized'] = true;

			if($self->_meta['source'] === null) 
				$self->_meta['source'] = Inflector::tableize($self->_meta['name']);
			$titleKeys = array('title', 'name');

			if(isset($self->_meta['key']))
				$titleKeys = array_merge($titleKeys, (array) $self->_meta['key']);
			$self->_meta['title'] = $self->_meta['title'] ?: static::hasField($titleKeys);
		}
		if(is_array($key) || !$key || $value) 
			return $self->_meta;

		return isset($self->_meta[$key]) ? $self->_meta[$key] : null;
	}

	public static function key($values = array()) 
	{
		$key = static::_object()->_meta['key'];

		if(is_object($values) && method_exists($values, 'to'))
			$values = $values->to('array');
		elseif (is_object($values) && is_string($key) && isset($values->{$key})) 
			return $values->{$key};

		if(!$values)
			return $key;
		if(!is_array($values) && !is_array($key)) 
			return array($key => $values);

		$key = (array) $key;        
		
		return array_intersect_key($values, array_combine($key, $key));
	}

	public static function relations($name = null) 
	{
		$self = static::_object();

		if(!$name)
			return $self->_relations;

		if(isset($self->_relationTypes[$name])) 
		{
			return array_keys(array_filter($self->_relations, function($i) use ($name) {
				return $i->data('type') == $name;
			}));
		}       
		
		return isset($self->_relations[$name]) ? $self->_relations[$name] : null;
	}

	public static function bind($type, $name, array $config = array()) 
	{
		$self = static::_object();

		if(!isset($self->_relationTypes[$type]))
			throw new ConfigException("Invalid relationship type `{$type}` specified.");

		$rel = static::connection()->relationship(get_called_class(), $type, $name, $config); 
		
		return $self->_relations[$name] = $rel;
	}

	public static function schema($field = null) 
	{
		$self = static::_object();

		if($field === false)
			return $self->_schema = array();
		if(!$self->_schema) 
		{
			$self->_schema = static::connection()->describe($self::meta('source'), $self->_meta);
			$key = (array) self::meta('key');
			if($self->_schema && array_intersect($key, array_keys($self->_schema)) != $key)
				throw new ConfigException('Missing key `' . implode(',', $key) . '` from schema.');
		}
		if(is_string($field) && $field)
			return isset($self->_schema[$field]) ? $self->_schema[$field] : null;

		return $self->_schema;
	}

	public static function hasField($field) 
	{
		if(is_array($field)) 
		{
			foreach($field as $f) {
				if(static::hasField($f))
					return $f;
			}    
			
			return false;
		}    
		$schema = static::schema();    
		
		return ($schema && isset($schema[$field]));
	}

	public static function create(array $data = array(), array $options = array()) 
	{
		$self   = static::_object();
		$params = compact('data', 'options');

		return static::_filter(__FUNCTION__, $params, function($self, $params) 
		{
			$data     = $params['data'];
			$options  = $params['options'];
			$defaults = array();

			foreach((array) $self::schema() as $field => $config) {
				if(isset($config['default']))
					$defaults[$field] = $config['default'];
			}                              
			
			$data = Set::merge(Set::expand($defaults), $data); 
			
			return $self::connection()->item($self, $data, $options);
		});
	}
 
  # WTF? Why is $entity a required param?      
  # I think what happends is we actually call save on an entity. 
  # It passes itself to this.
	public function save($entity, $data = null, array $options = array()) 
	{
		$self    = static::_object();
		$_meta   = array('model' => get_called_class()) + $self->_meta;
		$_schema = $self->_schema;

		$defaults = array(
			'validate' => true,
			'whitelist' => null,
			'callbacks' => true,
			'locked'    => $self->_meta['locked']
		);
		$options += $defaults;
		$params  = compact('entity', 'data', 'options');

		$filter = function($self, $params) use ($_meta, $_schema) 
		{
			$entity  = $params['entity'];
			$options = $params['options'];

			if($params['data']) 
				$entity->set($params['data']);
			if($rules = $options['validate']) {
				if(!$entity->validates(is_array($rules) ? compact('rules') : array())) 
					return false;
			}
			if(($whitelist = $options['whitelist']) || $options['locked']) 
				$whitelist = $whitelist ?: array_keys($_schema);

			$type      = $entity->exists() ? 'update' : 'create';
			$queryOpts = compact('type', 'whitelist', 'entity') + $options + $_meta;
			$query     = $self::invokeMethod('_instance', array('query', $queryOpts));      
			
			return $self::connection()->{$type}($query, $options);
		};

		if(!$options['callbacks'])
			return $filter(get_called_class(), $params);

		return static::_filter(__FUNCTION__, $params, $filter);
	}

	public function validates($entity, array $options = array()) 
	{
		$defaults = array(
			'rules'  => $this->validates,
			'events' => $entity->exists() ? 'update' : 'create',
			'model'  => get_called_class()
		);
		$options  += $defaults;
		$self      = static::_object();
		$validator = static::$_classes['validator'];
		$params    = compact('entity', 'options');

		$filter = function($parent, $params) use (&$self, $validator) 
		{
			$entity  = $params['entity'];
			$options = $params['options'];
			$rules   = $options['rules'];
			unset($options['rules']);

			if($errors = $validator::check($entity->data(), $rules, $options))
				$entity->errors($errors);

			return empty($errors);
		};        
		
		return static::_filter(__FUNCTION__, $params, $filter);
	}

	public function delete($entity, array $options = array()) 
	{
		$self   = static::_object();
		$params = compact('entity', 'options');

		return static::_filter(__FUNCTION__, $params, function($self, $params) 
		{
			$options = $params + $params['options'] + array('model' => $self, 'type' => 'delete');
			unset($options['options']);

			$query = $self::invokeMethod('_instance', array('query', $options));      
			
			return $self::connection()->delete($query, $options);
		});
	}

	public static function update($data, $conditions = array(), array $options = array()) 
	{
		$self   = static::_object();
		$params = compact('data', 'conditions', 'options');

		return static::_filter(__FUNCTION__, $params, function($self, $params) 
		{
			$options = $params + $params['options'] + array('model' => $self, 'type' => 'update');
			unset($options['options']);

			$query = $self::invokeMethod('_instance', array('query', $options));     
			
			return $self::connection()->update($query, $options);
		});
	}

	public static function remove($conditions = array(), array $options = array()) 
	{
		$self   = static::_object();
		$params = compact('conditions', 'options');

		return static::_filter(__FUNCTION__, $params, function($self, $params) 
		{
			$options = $params['options'] + $params + array('model' => $self, 'type' => 'delete');
			unset($options['options']);

			$query = $self::invokeMethod('_instance', array('query', $options));   
			
			return $self::connection()->delete($query, $options);
		});
	}

	public static function &connection() 
	{
		$self        = static::_object();
		$connections = static::$_classes['connections'];
		$name        = isset($self->_meta['connection']) ? $self->_meta['connection'] : null;

		if($conn = $connections::get($name))
			return $conn;

		throw new ConfigException("The data connection `{$name}` is not configured.");
	}

	protected static function _name() 
	{
		return basename(str_replace('\\', '/', get_called_class()));
	}

	public static function applyFilter($method, $closure = null) 
	{
		$instance = static::_object();
		$methods  = (array) $method;

		foreach($methods as $method) 
		{
			if(!isset($instance->_instanceFilters[$method]))
				$instance->_instanceFilters[$method] = array();

			$instance->_instanceFilters[$method][] = $closure;
		}
	}

	protected static function _filter($method, $params, $callback, $filters = array()) 
	{
		if(!strpos($method, '::')) 
			$method = get_called_class() . '::' . $method;

		list($class, $method) = explode('::', $method, 2);
		$instance             = static::_object();

		if(isset($instance->_instanceFilters[$method]))
			$filters = array_merge($instance->_instanceFilters[$method], $filters);

		return parent::_filter($method, $params, $callback, $filters);
	}

	protected static function &_object() 
	{
		$class = get_called_class();

		if(!isset(static::$_instances[$class]))
			static::$_instances[$class] = new $class();

		return static::$_instances[$class];
	}

	protected static function _relations() 
	{
		$self = static::_object();

		if(!$self->_meta['connection'])
			return;

		foreach($self->_relationTypes as $type => $keys) 
		{
			foreach(Set::normalize($self->{$type}) as $name => $config) {
				static::bind($type, $name, (array) $config);
			}
		}
	}

	protected static function _isBase($class = null, $set = false) 
	{
		if($set)
			static::$_baseClasses[$class] = true;

		return isset(static::$_baseClasses[$class]);
	}

	protected static function _findFilters()
	{
		$self = static::_object();
		$_query = $self->_query;

		return array(
			'first' => function($self, $params, $chain) {
				$params['options']['limit'] = 1;
				$data = $chain->next($self, $params, $chain);
				$data = is_object($data) ? $data->rewind() : $data;
				return $data ?: null;
			},
			'list' => function($self, $params, $chain) {
				$result = array();
				$meta   = $self::meta();
				$name   = $meta['key'];

				foreach($chain->next($self, $params, $chain) as $entity) {
					$key = $entity->{$name};
					$result[is_scalar($key) ? $key : (string) $key] = $entity->{$meta['title']};
				}
				return $result;
			},
			'count' => function($self, $params) use ($_query) 
			{
				$model   = $self;
				$type    = $params['type'];
				$options = array_diff_key($params['options'], $_query);

				if($options && !isset($params['options']['conditions']))
					$options = array('conditions' => $options);
			  else 
					$options = $params['options'];
				
				$options += array('type' => 'read') + compact('model');
				$query    = $self::invokeMethod('_instance', array('query', $options)); 
				
				return $self::connection()->calculation('count', $query, $options);
			}
		);
	}
}