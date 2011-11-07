<?php

namespace arthur\data\source\http\adapter;

use arthur\core\ConfigException;

class CouchDb extends \arthur\data\source\Http 
{
	protected $_iterator = 0;
	protected $_db = false;

	protected $_classes = array(
		'service' => 'arthur\net\http\Service',
		'entity'  => 'arthur\data\entity\Document',
		'set'     => 'arthur\data\collection\DocumentSet',
		'array'   => 'arthur\data\collection\DocumentArray'
	);

	protected $_handlers = array();

	public function __construct(array $config = array()) 
	{
		$defaults = array('port' => 5984, 'version' => 1);
		parent::__construct($config + $defaults);
	}

	protected function _init() 
	{
		parent::_init();
		$this->_handlers += array(
			'integer' => function($v) { return (integer) $v; },
			'float'   => function($v) { return (float) $v; },
			'boolean' => function($v) { return (boolean) $v; }
		);
	}

	public function __destruct() 
	{
		if(!$this->_isConnected)
			return;

		$this->disconnect();
		$this->_db = false;
		unset($this->connection);
	}

	public function configureClass($class)
	{
		return array(
			'meta' => array('key' => 'id', 'locked' => false),
			'schema' => array(
				'id'  => array('type' => 'string'),
				'rev' => array('type' => 'string')
			)
		);
	}

	public function __call($method, $params = array()) 
	{
		list($path, $data, $options) = ($params + array('/', array(), array()));
		return json_decode($this->connection->{$method}($path, $data, $options));
	}

	public function sources($class = null) { }

	public function describe($entity, array $meta = array()) 
	{
		$database = $this->_config['database'];

		if(!$this->_db) 
		{
			$result = $this->get($database);

			if(isset($result->db_name))
				$this->_db = true;
			if(!$this->_db) 
			{
				if(isset($result->error)) {
					if($result->error == 'not_found')
						$result = $this->put($database);
				}
				if(isset($result->ok) || isset($result->db_name))
					$this->_db = true;
			}
		}
		if(!$this->_db)
			throw new ConfigException("Database `{$entity}` is not available.");
	}

	public function name($name) 
	{
		return $name;
	}

	public function create($query, array $options = array()) 
	{
		$defaults = array('model' => $query->model());
		$options += $defaults;
		$params   = compact('query', 'options');
		$conn     =& $this->connection;
		$config   = $this->_config;

		return $this->_filter(__METHOD__, $params, function($self, $params) use (&$conn, $config) 
		{
			$request = array('type' => 'json');
			$query   = $params['query'];
			$options = $params['options'];
			$data    = $query->data();
			$data   += array('type' => $options['model']::meta('source'));

			if(isset($data['id']))
				return $self->update($query, $options);
				
			$result = $conn->post($config['database'], $data, $request);
			$result = is_string($result) ? json_decode($result, true) : $result;

			if(isset($result['_id']) || (isset($result['ok']) && $result['ok'] === true)) 
			{
				$result = $self->invokeMethod('_format', array($result, $options));
				$query->entity()->sync($result['id'], $result);
				return true;
			}
			return false;
		});
	}

	public function read($query, array $options = array()) 
	{
		$defaults = array('return' => 'resource', 'model' => $query->model());
		$options += $defaults;
		$params   = compact('query', 'options');
		$conn     =& $this->connection;
		$config   = $this->_config;

		return $this->_filter(__METHOD__, $params, function($self, $params) use (&$conn, $config) 
		{
			$query   = $params['query'];
			$options = $params['options'];
			$params  = $query->export($self);
			extract($params, EXTR_OVERWRITE);
			list($_path, $conditions) = (array) $conditions;

			if(empty($_path)) {
				$_path = '_all_docs';
				$conditions['include_docs'] = 'true';
			}
			$path   = "{$config['database']}/{$_path}";
			$args   = (array) $conditions + (array) $limit + (array) $order;
			$result = (array) json_decode($conn->get($path, $args), true);
			$data   = $stats = array();

			if(isset($result['_id'])) {
				$data = array($result);
			} 
			elseif(isset($result['rows'])) {
				$data = $result['rows'];
				unset($result['rows']);
				$stats = $result;
			}

			$stats += array('total_rows' => null, 'offset' => null);
			$opts   = compact('stats') + array('class' => 'set', 'exists' => true);
			return $self->item($query->model(), $data, $opts);
		});
	}
	public function update($query, array $options = array()) 
	{
		$params = compact('query', 'options');
		$conn   =& $this->connection;
		$config = $this->_config;

		return $this->_filter(__METHOD__, $params, function($self, $params) use (&$conn, $config) 
		{
			$query   = $params['query'];
			$options = $params['options'];
			$params  = $query->export($self);
			extract($params, EXTR_OVERWRITE);
			list($_path, $conditions) = (array) $conditions;
			$data = $query->data();

			foreach(array('id', 'rev') as $key) {
				$data["_{$key}"] = isset($data[$key]) ? (string) $data[$key] : null;
				unset($data[$key]);
			}
			$data   = (array) $conditions + array_filter((array) $data);
			$result = $conn->put("{$config['database']}/{$_path}", $data, array('type' => 'json'));
			$result = is_string($result) ? json_decode($result, true) : $result;

			if(isset($result['_id']) || (isset($result['ok']) && $result['ok'] === true)) 
			{
				$result = $self->invokeMethod('_format', array($result, $options));
				$query->entity()->sync($result['id'], array('rev' => $result['rev']));
				return true;
			}
			if(isset($result['error']))
				$query->entity()->errors(array($result['error']));

			return false;
		});
	}

	public function delete($query, array $options = array()) 
	{
		$params = compact('query', 'options');
		$conn   =& $this->connection;
		$config = $this->_config;

		return $this->_filter(__METHOD__, $params, function($self, $params) use (&$conn, $config) 
		{
			$query  = $params['query'];
			$params = $query->export($self);
			list($_path, $conditions) = $params['conditions'];
			$data = $query->data();

			if(!empty($data['rev']))
				$conditions['rev'] = $data['rev'];
				
			$result = json_decode($conn->delete("{$config['database']}/{$_path}", $conditions));
			return (isset($result->ok) && $result->ok === true);
		});
	}

	public function calculation($type, $query, array $options = array()) 
	{
		switch($type) 
		{
			case 'count':
				return $this->read($query, $options)->stats('total_rows');
			default:
				return null;
		}
	}

	public function item($model, array $data = array(), array $options = array()) 
	{
		if(isset($data['doc']))
			return parent::item($model, $this->_format($data['doc']), $options);
		if(isset($data['value']))
			return parent::item($model, $this->_format($data['value']), $options);

		return parent::item($model, $this->_format($data), $options);
	}

	public function cast($entity, array $data, array $options = array()) 
	{
		$defaults = array('pathKey' => null, 'model' => null);
		$options += $defaults;
		$model    = $options['model'] ?: $entity->model();

		foreach($data as $key => $val) 
		{
			if(!is_array($val))
				continue;

			$pathKey    = $options['pathKey'] ? "{$options['pathKey']}.{$key}" : $key;
			$class      = (range(0, count($val) - 1) === array_keys($val)) ? 'array' : 'entity';
			$data[$key] = $this->item($model, $val, compact('class', 'pathKey') + $options);
		}
		return parent::cast($entity, $data, $options);
	}

	public function conditions($conditions, $context) 
	{
		$path = null;
		if(isset($conditions['design'])) 
		{
			$paths = array('design', 'view');
			foreach($paths as $element) 
			{
				if(isset($conditions[$element])) {
					$path .= "_{$element}/{$conditions[$element]}/";
					unset($conditions[$element]);
				}
			}
		}
		if(isset($conditions['id'])) {
			$path = "{$conditions['id']}";
			unset($conditions['id']);
		}
		if(isset($conditions['path'])) {
			$path = "{$conditions['path']}";
			unset($conditions['path']);
		}  
		
		return array($path, $conditions);
	}

	public function fields($fields, $context) 
	{
		return $fields ?: array();
	}

	public function limit($limit, $context) 
	{
		return compact('limit') ?: array();
	}

	public function order($order, $context) 
	{
		return (array) $order ?: array();
	}

	public static function enabled($feature = null) 
	{
		if(!$feature)
			return true;

		$features = array(
			'arrays'        => true,
			'transactions'  => false,
			'booleans'      => true,
			'relationships' => false
		);
		return isset($features[$feature]) ? $features[$feature] : null;
	}

	protected function _format(array $data) 
	{
		if(isset($data['_id']))
			$data['id'] = $data['_id'];
		if(isset($data['_rev']))
			$data['rev'] = $data['_rev'];

		unset($data['_id'], $data['_rev']);
		return $data;
	}
}