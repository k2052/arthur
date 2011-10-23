<?php

namespace lithium\data\source;

use Mongo;
use MongoId;
use MongoCode;
use MongoDate;
use MongoRegex;
use MongoBinData;
use lithium\util\Inflector;
use lithium\core\NetworkException;
use Exception;

class MongoDb extends \lithium\data\Source
{
	public $server = null;
	public $connection = null;

	protected $_classes = array(
		'entity'       => 'lithium\data\entity\Document',
		'array'        => 'lithium\data\collection\DocumentArray',
		'set'          => 'lithium\data\collection\DocumentSet',
		'result'       => 'lithium\data\source\mongo_db\Result',
		'exporter'     => 'lithium\data\source\mongo_db\Exporter',
		'relationship' => 'lithium\data\model\Relationship'
	);

	protected $_operators = array(
		'<'   => '$lt',
		'>'   => '$gt',
		'<='  =>  '$lte',
		'>='  => '$gte',
		'!='  => array('single' => '$ne', 'multiple' => '$nin'),
		'<>'  => array('single' => '$ne', 'multiple' => '$nin'),
		'or'  => '$or',
		'||'  => '$or',
		'not' => '$not',
		'!'   =>  '$not'
	);
	
	protected $_schema = null;
	protected $_handlers = array();
	protected $_autoConfig = array('schema', 'handlers', 'classes' => 'merge');

	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'persistent' => false,
			'login'      => null,
			'password'   => null,
			'host'       => Mongo::DEFAULT_HOST . ':' . Mongo::DEFAULT_PORT,
			'database'   => null,
			'timeout'    => 100,
			'replicaSet' => false,
			'schema'     => null,
			'gridPrefix' => 'fs'
		);     
		
		parent::__construct($config + $defaults);
	}

	protected function _init() 
	{
		parent::_init();

		$this->_operators += array(
			'like' => function($key, $value) { return new MongoRegex($value); }
		);

		$this->_handlers += array(
			'id' => function($v) 
			{
				return is_string($v) && preg_match('/^[0-9a-f]{24}$/', $v) ? new MongoId($v) : $v;
			},
			'date' => function($v) 
			{
				$v = is_numeric($v) ? intval($v) : strtotime($v);
				return (!$v || time() == $v) ? new MongoDate() : new MongoDate($v);
			},
			'regex'   => function($v) { return new MongoRegex($v); },
			'integer' => function($v) { return (integer) $v; },
			'float'   => function($v) { return (float) $v; },
			'boolean' => function($v) { return (boolean) $v; },
			'code'    => function($v) { return new MongoCode($v); },
			'binary'  => function($v) { return new MongoBinData($v); }
		);
	}

	public function __destruct() 
	{
		if($this->_isConnected)
			$this->disconnect();
	}

	public static function enabled($feature = null) 
	{
		if(!$feature)
			return extension_loaded('mongo');

		$features = array(
			'arrays'        => true,
			'transactions'  => false,
			'booleans'      => true,
			'relationships' => true
		);                             
		
		return isset($features[$feature]) ? $features[$feature] : null;
	}

	public function configureClass($class) 
	{
		return array(
			'meta'   => array('key' => '_id', 'locked' => false),
			'schema' => array()
		);
	}

	public function connect() 
	{
		$cfg = $this->_config;
		$this->_isConnected = false;

		$host       = is_array($cfg['host']) ? join(',', $cfg['host']) : $cfg['host'];
		$login      = $cfg['login'] ? "{$cfg['login']}:{$cfg['password']}@" : '';
		$connection = "mongodb://{$login}{$host}" . ($login ? "/{$cfg['database']}" : ''); 
		
		$options = array(
			'connect' => true, 'timeout' => $cfg['timeout'], 'replicaSet' => $cfg['replicaSet']
		);

		try 
		{
			if($persist = $cfg['persistent'])
				$options['persist'] = $persist === true ? 'default' : $persist;
			$this->server = new Mongo($connection, $options);

			if($this->connection = $this->server->{$cfg['database']})
				$this->_isConnected = true;
		} 
		catch(Exception $e) {
			throw new NetworkException("Could not connect to the database.", 503, $e);
		}   
		
		return $this->_isConnected;
	}

	public function disconnect() 
	{
		if($this->server && $this->server->connected) 
		{
			try {
				$this->_isConnected = !$this->server->close();
			} 
			catch (Exception $e) { } 
			
			unset($this->connection, $this->server);
			return !$this->_isConnected;
		}  
		
		return true;
	}

	public function sources($class = null) 
	{
		$this->_checkConnection();
		$conn = $this->connection;  
		
		return array_map(function($col) { return $col->getName(); }, $conn->listCollections());
	}

	public function describe($entity, array $meta = array()) 
	{
		if(!$schema = $this->_schema)
			return array();

		return $schema($this, $entity, $meta);
	}

	public function name($name) 
	{
		return $name;
	}

	public function __call($method, $params) 
	{
		if((!$this->server) && !$this->connect())
			return null;
			
		return call_user_func_array(array(&$this->server, $method), $params);
	}

	public function schema($query, $resource = null, $context = null) 
	{
		return array();
	}

	public function create($query, array $options = array()) 
	{
		$defaults = array('safe' => false, 'fsync' => false);
		$options += $defaults;
		$this->_checkConnection();

		$params  = compact('query', 'options');
		$_config = $this->_config;
		$_exp    = $this->_classes['exporter'];

		return $this->_filter(__METHOD__, $params, function($self, $params) use ($_config, $_exp) 
		{
			$query   = $params['query'];
			$options = $params['options'];

			$args    = $query->export($self, array('keys' => array('source', 'data')));
			$data    = $_exp::get('create', $args['data']);
			$source  = $args['source'];

			if($source == "{$_config['gridPrefix']}.files" && isset($data['create']['file'])) {
				$result = array('ok' => true);
				$data['create']['_id'] = $self->invokeMethod('_saveFile', array($data['create']));
			} 
			else
				$result = $self->connection->{$source}->insert($data['create'], $options);

			if($result === true || isset($result['ok']) && (boolean) $result['ok'] === true) 
			{
				if($query->entity())
					$query->entity()->sync($data['create']['_id']);

				return true;
			}  
			
			return false;
		});
	}

	protected function _saveFile($data) 
	{
		$uploadKeys = array('name', 'type', 'tmp_name', 'error', 'size');
		$grid       = $this->connection->getGridFS();
		$file       = null;
		$method     = null;

		switch(true) 
		{
			case  (is_array($data['file']) && array_keys($data['file']) == $uploadKeys):
				if (!$data['file']['error'] && is_uploaded_file($data['file']['tmp_name'])) {
					$method = 'storeFile';
					$file = $data['file']['tmp_name'];
					$data['filename'] = $data['file']['name'];
				}
			break;
			case (is_string($data['file']) && file_exists($data['file'])):
				$method = 'storeFile';
				$file = $data['file'];
			break;
			case $data['file']:
				$method = 'storeBytes';
				$file = $data['file'];
			break;
		}

		if(!$method || !$file)
			return;

		if(isset($data['_id'])) {
			$data += (array) get_object_vars($grid->get($data['_id']));
			$grid->delete($data['_id']);
		} 
		
		unset($data['file']);
		return $grid->{$method}($file, $data);
	}

	public function read($query, array $options = array()) 
	{
		$this->_checkConnection();
		$defaults = array('return' => 'resource');
		$options += $defaults;

		$params  = compact('query', 'options');
		$_config = $this->_config;

		return $this->_filter(__METHOD__, $params, function($self, $params) use ($_config) 
		{
			$query   = $params['query'];
			$options = $params['options'];
			$args    = $query->export($self);
			$source  = $args['source'];

			if($group = $args['group']) 
			{
				$result = $self->invokeMethod('_group', array($group, $args, $options));
				$config = array('class' => 'set') + compact('query') + $result;
				return $self->item($query->model(), $config['data'], $config);
			}
			$collection = $self->connection->{$source};

			if($source == "{$_config['gridPrefix']}.files")
				$collection = $self->connection->getGridFS();

			$result = $collection->find($args['conditions'], $args['fields']);

			if($query->calculate())
				return $result;

			$resource = $result->sort($args['order'])->limit($args['limit'])->skip($args['offset']);
			$result   = $self->invokeMethod('_instance', array('result', compact('resource')));
			$config   = compact('result', 'query') + array('class' => 'set');   
			
			return $self->item($query->model(), array(), $config);
		});
	}

	protected function _group($group, $args, $options) 
	{
		$conditions = $args['conditions'];
		$group     += array('$reduce' => $args['reduce'], 'initial' => $args['initial']);
		$command    = array('group' => $group + array('ns' => $args['source'], 'cond' => $conditions));

		$stats = $this->connection->command($command);
		$data  = isset($stats['retval']) ? $stats['retval'] : null;
		unset($stats['retval']);      
		
		return compact('data', 'stats');
	}

	public function update($query, array $options = array()) 
	{
		$defaults = array('upsert' => false, 'multiple' => true, 'safe' => false, 'fsync' => false);
		$options += $defaults;
		$this->_checkConnection();

		$params   = compact('query', 'options');
		$_config  = $this->_config;
		$_exp     = $this->_classes['exporter'];

		return $this->_filter(__METHOD__, $params, function($self, $params) use ($_config, $_exp) 
		{
			$options = $params['options'];
			$query   = $params['query'];
			$args    = $query->export($self, array('keys' => array('conditions', 'source', 'data')));
			$source  = $args['source'];
			$data    = $args['data'];
               
			if($query->entity()) 
				$data = $_exp::get('update', $data);

			if($source == "{$_config['gridPrefix']}.files" && isset($data['update']['file']))
				$args['data']['_id'] = $self->invokeMethod('_saveFile', array($data['update']));
			$update = $query->entity() ? $_exp::toCommand($data) : $data;

			if($options['multiple'] && !preg_grep('/^\$/', array_keys($update)))
				$update = array('$set' => $update);
			if($self->connection->{$source}->update($args['conditions'], $update, $options)) 
			{
				$query->entity() ? $query->entity()->sync() : null;
				return true;
			}   
			
			return false;
		});
	}

	public function delete($query, array $options = array()) 
	{
		$this->_checkConnection();
		$defaults = array('justOne' => false, 'safe' => false, 'fsync' => false);
		$options  = array_intersect_key($options + $defaults, $defaults);

		return $this->_filter(__METHOD__, compact('query', 'options'), function($self, $params) 
		{
			$query   = $params['query'];
			$options = $params['options'];
			$args    = $query->export($self, array('keys' => array('source', 'conditions')));  
			
			return $self->connection->{$args['source']}->remove($args['conditions'], $options);
		});
	}
	
	public function calculation($type, $query, array $options = array()) 
	{
		$query->calculate($type);

		switch($type) 
		{
			case 'count':
				return $this->read($query, $options)->count();
		}
	}

	public function relationship($class, $type, $name, array $config = array()) 
	{
		$key = Inflector::camelize($type == 'belongsTo' ? $class::meta('name') : $name, false);

		$config         += compact('name', 'type', 'key');
		$config['from']  = $class;
		$relationship    = $this->_classes['relationship'];

		$defaultLinks = array(
			'hasOne'    => $relationship::LINK_EMBEDDED,
			'hasMany'   => $relationship::LINK_EMBEDDED,
			'belongsTo' => $relationship::LINK_CONTAINED
		);
		$config += array('link' => $defaultLinks[$type]);  
		
		return new $relationship($config);
	}

	public function group($group, $context) 
	{
		if(!$group)
			return;
		if(is_string($group) && strpos($group, 'function') === 0)
			return array('$keyf' => new MongoCode($group));
		$group = (array) $group;

		foreach($group as $i => $field) 
		{
			if(is_int($i)) {
				$group[$field] = true;
				unset($group[$i]);
			}
		}        
		
		return array('key' => $group);
	}

	public function conditions($conditions, $context) 
	{
		$schema = array();
		$model  = null;

		if(!$conditions)
			return array();
		if($code = $this->_isMongoCode($conditions))
			return $code; 
			
		if($context) {
			$model  = $context->model();
			$schema = $context->schema();
		}     
		
		return $this->_conditions($conditions, $model, $schema, $context);
	}

	protected function _conditions($conditions, $model, $schema, $context) 
	{
		$castOpts = compact('schema') + array('first' => true, 'arrays' => false);

		foreach($conditions as $key => $value) 
		{
			if($key === '$or' || $key === 'or' || $key === '||') 
			{
				foreach($value as $i => $or) {
					$value[$i] = $this->_conditions($or, $model, $schema, $context);
				}
				unset($conditions[$key]);
				$conditions['$or'] = $value;
				continue;
			}
			if(is_object($value))
				continue;
			if(!is_array($value)) {
				$conditions[$key] = $this->cast(null, array($key => $value), $castOpts);
				continue;
			}
			$current   = key($value);
			$isOpArray = (isset($this->_operators[$current]) || $current[0] === '$');

			if(!$isOpArray) 
			{
				$data             = array($key => $value);
				$conditions[$key] = array('$in' => $this->cast($model, $data, $castOpts));
				continue;
			}
			$operations = array();

			foreach($value as $op => $val) 
			{
				if(is_object($result = $this->_operator($model, $key, $op, $val, $schema))) {
					$operations = $result;
					break;
				}
				$operations += $this->_operator($model, $key, $op, $val, $schema);
			} 
			
			$conditions[$key] = $operations;
		}     
		
		return $conditions;
	}

	protected function _isMongoCode($conditions) 
	{
		if($conditions instanceof MongoCode)
			return array('$where' => $conditions);
		if(is_string($conditions))
			return array('$where' => new MongoCode($conditions));
	}

	protected function _operator($model, $key, $op, $value, $schema) 
	{
		$castOpts = compact('schema') + array('first' => true, 'arrays' => false);

		switch(true) 
		{
			case !isset($this->_operators[$op]):
				return array($op => $this->cast($model, array($key => $value), $castOpts));
			case is_callable($this->_operators[$op]):
				return $this->_operators[$op]($key, $value);
			case is_array($this->_operators[$op]):
				$format = (is_array($value)) ? 'multiple' : 'single';
				$operator = $this->_operators[$op][$format];
			break;
			default:
				$operator = $this->_operators[$op];
			break;
		}      
		
		return array($operator => $value);
	}

	public function fields($fields, $context) 
	{
		return $fields ?: array();
	}
	
	public function limit($limit, $context) 
	{
		return $limit ?: 0;
	}

	public function order($order, $context) 
	{
		switch(true) 
		{
			case !$order:
				return array();
			case is_string($order):
				return array($order => 1);
			case is_array($order):
				foreach($order as $key => $value) 
				{
					if(!is_string($key)) 
					{
						unset($order[$key]);
						$order[$value] = 1;
						continue;
					}
					if(is_string($value))
						$order[$key] = strtoupper($value) == 'ASC' ? 1 : -1;
				}
			break;
		}      
		
		return $order ?: array();
	}

	public function cast($entity, array $data, array $options = array()) 
	{
		$defaults = array('schema' => null, 'first' => false);
		$options += $defaults;
		$model    = null;
		$exists   = false;

		if(!$data) 
			return $data;

		if(is_string($entity)) 
		{
			$model             = $entity;
			$entity            = null;
			$options['schema'] = $options['schema'] ?: $model::schema();
		} 
		elseif($entity) 
		{
			$options['schema'] = $options['schema'] ?: $entity->schema();
			$model             = $entity->model();

			if(is_a($entity, $this->_classes['entity']))
				$exists = $entity->exists();
		}
		$schema = $options['schema'] ?: array('_id' => array('type' => 'id'));
		unset($options['schema']);

		$exporter = $this->_classes['exporter'];
		$options += compact('model', 'exists') + array('handlers' => $this->_handlers);    
		
		return parent::cast($entity, $exporter::cast($data, $schema, $this, $options), $options);
	}

	protected function _checkConnection() 
	{
		if(!$this->_isConnected && !$this->connect())
			throw new NetworkException("Could not connect to the database.");
	}
}