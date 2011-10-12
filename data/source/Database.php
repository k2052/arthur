<?php

namespace lithium\data\source;

use lithium\util\String;
use lithium\util\Inflector;
use InvalidArgumentException;

abstract class Database extends \lithium\data\Source 
{
	protected $_columns = array(
		'string' => array('length' => 255)
	);

	protected $_strings = array(
		'create' => "INSERT INTO {:source} ({:fields}) VALUES ({:values});{:comment}",
		'update' => "UPDATE {:source} SET {:fields} {:conditions};{:comment}",
		'delete' => "DELETE {:flags} FROM {:source} {:conditions};{:comment}",
		'schema' => "CREATE TABLE {:source} (\n{:columns}{:indexes});{:comment}",
		'join'   => "{:type} JOIN {:source} {:alias} {:constraint}"
	);

	protected $_classes = array(
		'entity'       => 'lithium\data\entity\Record',
		'set'          => 'lithium\data\collection\RecordSet',
		'relationship' => 'lithium\data\model\Relationship'
	);

	protected $_operators = array(
		'='        => array('multiple' => 'IN'),
		'<'        => array(),
		'>'        => array(),
		'<='       => array(),
		'>='       => array(),
		'!='       => array('multiple' => 'NOT IN'),
		'<>'       => array('multiple' => 'NOT IN'),
		'between'  => array('format' => 'BETWEEN ? AND ?'),
		'BETWEEN'  => array('format' => 'BETWEEN ? AND ?'),
		'like'     => array(),
		'LIKE'     => array(),
		'not like' => array(),
		'NOT LIKE' => array()
	);

	protected $_constraintTypes = array(
		'AND' => true,
		'and' => true,
		'OR'  => true,
		'or'  => true
	);

	protected $_quotes = array();

	abstract public function encoding($encoding = null);
	abstract public function error();
	abstract protected function _execute($sql);
	abstract protected function _insertId($query);

	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'persistent' => true,
			'host'       => 'localhost',
			'login'      => 'root',
			'password'   => '',
			'database'   => null
		);   
		
		$this->_strings += array(
			'read' => 'SELECT {:fields} FROM {:source} {:alias} {:joins} {:conditions} {:group} ' .
			          '{:order} {:limit};{:comment}'
		);     
		
		parent::__construct($config + $defaults);
	}

	public function name($name) 
	{
		$open  = reset($this->_quotes);
		$close = next($this->_quotes);      
		
		if(preg_match('/^[a-z0-9_-]+\.[a-z0-9_-]+$/i', $name)) {
			list($first, $second) = explode('.', $name, 2);
			return "{$open}{$first}{$close}.{$open}{$second}{$close}";
		}      
		
		return preg_match('/^[a-z0-9_-]+$/i', $name) ? "{$open}{$name}{$close}" : $name;
	}

	public function value($value, array $schema = array()) 
	{
		if(is_array($value)) 
		{
			foreach($value as $key => $val) {
				$value[$key] = $this->value($val, isset($schema[$key]) ? $schema[$key] : $schema);
			}  
			
			return $value;
		}           
		
		if($value === null) return 'NULL';  
		
		switch ($type = isset($schema['type']) ? $schema['type'] : $this->_introspectType($value)) 
		{
			case 'boolean':
				return $this->_toNativeBoolean($value);
			case 'float':
				return floatval($value);
			case 'integer':
				return intval($value);
		}
	}
	
	public function create($query, array $options = array()) 
	{
		return $this->_filter(__METHOD__, compact('query', 'options'), function($self, $params) 
		{
			$query = $params['query'];
			$model = $entity = $object = $id = null;

			if(is_object($query)) 
			{
				$object = $query;
				$model  = $query->model();
				$params = $query->export($self);
				$entity =& $query->entity();
				$query  = $self->renderCommand('create', $params, $query);
			}
			else
				$query = String::insert($query, $self->value($params['options']));

			if(!$self->invokeMethod('_execute', array($query)))
				return false;

			if($entity) 
			{
				if(($model) && !$model::key($entity)) 
					$id = $self->invokeMethod('_insertId', array($object));

				$entity->sync($id);
			}        
			
			return true;
		});
	}

	public function read($query, array $options = array()) 
	{
		$defaults = array(
			'return' => is_string($query) ? 'array' : 'item', 'schema' => array()
		);
		$options += $defaults;

		return $this->_filter(__METHOD__, compact('query', 'options'), function($self, $params) 
		{
			$query  = $params['query'];
			$args   = $params['options'];
			$return = $args['return'];
			unset($args['return']);

			$model = is_object($query) ? $query->model() : null;

			if(is_string($query))
				$sql = String::insert($query, $self->value($args));
			else 
			{
				$limit = $query->limit();
				if($model && $limit && !isset($args['subquery']) && $model::relations('hasMany')) 
				{
					$name = $model::meta('name');
					$key  = $model::key();

					$subQuery = $self->invokeMethod('_instance', array(
							get_class($query), array(
								'type'       => 'read',
								'model'      => $model,
								'group'      => "{$name}.{$key}",
								'fields'     => array("{$name}.{$key}"),
								'joins'      => $query->joins(),
								'conditions' => $query->conditions(),
								'limit'      => $query->limit(),
								'page'       => $query->page(),
								'order'      => $query->order()
							)
						));
					$ids = $self->read($subQuery, array('subquery' => true));
					
					if(!$ids->count()) return false;

					$idData = $ids->data();    
					
					$ids = array_map(function($index) use ($key) {
							return $index[$key];
						}, $idData);      
						
					$query->limit(false)->conditions(array("{$name}.{$key}" => $ids));
				}       
				
				$sql = $self->renderCommand($query);
			}   
			
			$result = $self->invokeMethod('_execute', array($sql));

			switch($return) 
			{
				case 'resource':
					return $result;
				case 'array':
					$columns = $args['schema'] ?: $self->schema($query, $result);
					$records = array(); 
					
					if(is_array(reset($columns))) 
						$columns = reset($columns);  
						
					while($data = $result->next()) 
					{
						if(count($columns) != count($data) && is_array(current($columns)))
							$columns = current($columns);

						$records[] = array_combine($columns, $data);
					}        
					
					return $records;
				case 'item':
					return $self->item($query->model(), array(), compact('query', 'result') + array(
						'class' => 'set'
					));
			}
		});
	}

	public function update($query, array $options = array()) 
	{
		return $this->_filter(__METHOD__, compact('query', 'options'), function($self, $params) 
		{
			$query  = $params['query'];
			$params = $query->export($self);
			$sql    = $self->renderCommand('update', $params, $query);

			if($self->invokeMethod('_execute', array($sql))) 
			{
				if($query->entity()) 
					$query->entity()->sync();

				return true;
			}  
			
			return false;
		});
	}

	public function delete($query, array $options = array()) 
	{
		return $this->_filter(__METHOD__, compact('query', 'options'), function($self, $params) 
		{
			$query = $params['query'];

			if(is_object($query)) {
				$data = $query->export($self);
				$sql  = $self->renderCommand('delete', $data, $query);
			} 
			else
				$sql = String::insert($query, $self->value($params['options']));  
				
			return (boolean) $self->invokeMethod('_execute', array($sql));
		});
	}

	public function calculation($type, $query, array $options = array()) 
	{
		$query->calculate($type);

		switch($type) 
		{
			case 'count':
				if(strpos($fields = $this->fields($query->fields(), $query), ',') !== false)
					$fields = "*";

				$query->fields("COUNT({$fields}) as count", true);
				$query->map(array($query->alias() => array('count')));    
				
				list($record) = $this->read($query, $options)->data();  
				
				return isset($record['count']) ? intval($record['count']) : null;
		}
	}

	public function relationship($class, $type, $name, array $config = array()) 
	{
		$field   = Inflector::underscore(Inflector::singularize($name));
		$key     = "{$field}_id";
		$primary = $class::meta('key');

		if(is_array($primary))
			$key = array_combine($primary, $primary);
		elseif($type == 'hasMany' || $type == 'hasOne') 
		{
			if($type == 'hasMany') 
				$field = Inflector::pluralize($field);

			$secondary = Inflector::underscore(Inflector::singularize($class::meta('name')));
			$key       = array($primary => "{$secondary}_id");
		}

		$from      = $class;
		$fieldName = $field;
		$config   += compact('type', 'name', 'key', 'from', 'fieldName');  
		
		return $this->_instance('relationship', $config);
	}

	public function renderCommand($type, $data = null, $context = null) 
	{
		if(is_object($type)) 
		{
			$context = $type;
			$data    = $context->export($this);
			$type    = $context->type();
		}
		if(!isset($this->_strings[$type]))
			throw new InvalidArgumentException("Invalid query type `{$type}`.");

		$data = array_filter($data);  
		
		return trim(String::insert($this->_strings[$type], $data, array('clean' => true)));
	}

	public function schema($query, $resource = null, $context = null) 
	{
		$model     = is_scalar($resource) ? $resource : $query->model();
		$modelName = (method_exists($context, 'alias') ? $context->alias() : $query->alias());
		$fields    = $query->fields();
		$joins     = (array) $query->joins();
		$result    = array();

		if(!$model && is_array($fields))
			return array($fields);

		if(!$fields && !$joins) 
			return array($modelName => array_keys($model::schema()));

		if(!$fields && $joins) 
		{
			$return = array($modelName => array_keys($model::schema()));   
			
			foreach($joins as $join) {
				$model = $join->model();
				$return[$join->alias()] = array_keys($model::schema());
			}         
			
			return $return;
		}

		$relations    = array_keys((array) $query->relationships());
		$schema       = $model::schema();
		$pregDotMatch = '/^(' . implode('|', array_merge($relations, array($modelName))) . ')\./';
		$forJoin      = ($modelName != $query->alias());

		foreach($fields as $scope => $field) 
		{
			switch(true) 
			{
				case (is_numeric($scope) && ($field == '*' || $field == $modelName)):
					$result[$modelName] = array_keys($model::schema());
				break;
				case (is_numeric($scope) && isset($schema[$field])):
					$result[$modelName][] = $field;
				break;
				case is_numeric($scope) && preg_match($pregDotMatch, $field):
					list($dotModelName, $field) = explode('.', $field);
					$result[$dotModelName][] = $field;
					break;
				case is_array($field) && $scope == $modelName:
					$result[$modelName] = $field;
				break;
				case $forJoin || !$joins;
					continue;
				case in_array($scope, $relations) && is_array($field):
					$join = isset($joins[$scope]) ? $joins[$scope] : null;
					if($join) {
						$relSchema      = $this->schema($query, $join->model(), $join);
						$result[$scope] = reset($relSchema);
					}
				break;
				case is_numeric($scope) && in_array($field, $relations):
					$join = isset($joins[$field]) ? $joins[$field] : null;       
					
					if(!$join) continue;     
					
					$scope          = $join->model();
					$result[$field] = array_keys($scope::schema());
				break;
			}
		}  
		
		if(!$forJoin) 
		{
			$sortOrder = array_flip(array_merge(array($modelName), $relations));
			uksort($result, function($a, $b) use ($sortOrder) 
			 {
				return $sortOrder[$a] - $sortOrder[$b];
			});
		}   
		
		return $result;
	}

	public function conditions($conditions, $context, array $options = array()) 
	{
		$defaults = array('prepend' => true);
		$ops      = $this->_operators;
		$options += $defaults;
		$model    = $context->model();
		$schema   = $model ? $model::schema() : array();

		switch(true) 
		{
			case empty($conditions):
				return '';
			case is_string($conditions):
				return ($options['prepend']) ? "WHERE {$conditions}" : $conditions;
			case !is_array($conditions):
				return '';
		}
		$result = array();

		foreach($conditions as $key => $value) 
		{
			$schema[$key] = isset($schema[$key]) ? $schema[$key] : array();
			$return       = $this->_processConditions($key,$value, $schema);

			if($return) $result[] = $return;
		}
		$result = join(" AND ", $result); 
		
		return ($options['prepend'] && $result) ? "WHERE {$result}" : $result;
	}

	public function _processConditions($key, $value, $schema, $glue = 'AND') 
	{
		$constraintTypes =& $this->_constraintTypes;

		switch(true) 
		{
			case (is_numeric($key) && is_string($value)):
				return $value;
			case is_string($value):
				return $this->name($key) . ' = ' . $this->value($value, $schema[$key]);
			case is_numeric($key) && is_array($value):
				$result = array();     
				
				foreach($value as $cField => $cValue) {
					$result[] = $this->_processConditions($cField, $cValue, $schema, $glue);
				}     
				
				return '(' . implode(' ' . $glue . ' ', $result) . ')';
			case (is_string($key) && is_object($value)):
				$value = trim(rtrim($this->renderCommand($value), ';'));
				return "{$key} IN ({$value})";
			case is_array($value) && isset($constraintTypes[strtoupper($key)]):
				$result = array();
				$glue   = strtoupper($key);

				foreach($value as $cField => $cValue) {
					$result[] = $this->_processConditions($cField, $cValue, $schema, $glue);
				}     
				
				return '(' . implode(' ' . $glue . ' ', $result) . ')';
			case (is_string($key) && is_array($value) && isset($this->_operators[key($value)])):
				foreach($value as $op => $val) {
					$result[] = $this->_operator($key, array($op => $val), $schema[$key]);
				}      
				
				return '(' . implode(' ' . $glue . ' ', $result) . ')';
			case is_array($value):
				$value = join(', ', $this->value($value, $schema[$key]));  
				
				return "{$key} IN ({$value})";
			default:
				if(isset($value)) 
				{
					$value = $this->value($value, $schema[$key]);      
					
					return "{$key} = {$value}";
				}             
				
				if($value === null) return "{$key} IS NULL";
		}
	}

	public function fields($fields, $context) 
	{
		$type       = $context->type();
		$schema     = (array) $context->schema();
		$modelNames = (array) $context->name();
		$modelNames = array_merge($modelNames, array_keys((array) $context->relationships()));

		if(!is_array($fields))
			return $this->_fieldsReturn($type, $context, $fields, $schema);         
			
		$toMerge = array();
		$keys    = array_keys($fields);

		$groupFields = function($item, $key) use (&$toMerge, &$keys, $modelNames, &$context) 
		{
			$name = current($keys);
			next($keys);
			switch(true) 
			{
				case is_array($item):
					$toMerge[$name] = $item;
					continue;
				case in_array($item, $modelNames):
					if($item == reset($modelNames))
						$schema = $context->schema();
					else {
						$joins  = $context->joins();
						$schema = $joins[$item]->schema();
					}          
					
					$toMerge[$item] = array_keys($schema);
					continue;
				case strpos($item, '.') !== false:
					list($name, $field) = explode('.', $item);
					$toMerge[$name][] = $field;
					continue;
				default:
					$mainSchema = array_keys((array)$context->schema());
					if(in_array($item, $mainSchema)) {
						$toMerge[reset($modelNames)][] = $item;
						continue;
					}
					$toMerge[0][] = $item;
					continue;
			}
		};       
		
		array_walk($fields, $groupFields);
		$fields = $toMerge;

		if(count($modelNames) > 1) 
		{
			$sortOrder = array_flip($modelNames);
			uksort($fields, function($a, $b) use ($sortOrder) 
			{
				return $sortOrder[$a] - $sortOrder[$b];
			});
		}            
		
		$mapFields = function() use($fields, $modelNames) 
		{
			$return = array();           
			
			foreach($fields as $key => $items) 
			{
				if(!is_array($items)) {
					$return[$key] = $items;
					continue;
				}
				if(is_numeric($key))
					$key = reset($modelNames);

				$pointer = &$return[$key];
				foreach($items as $field) 
				{
					if(stripos($field, ' as ') !== false) 
					{
						list($real, $as) = explode(' as ', str_replace(' AS ', ' as ', $field));
						$pointer[] = trim($as);
						continue;
					}
					$pointer[] = $field;
				}
			}          
			
			return $return;
		};
		$context->map($mapFields());

		$toMerge = array();
		foreach($fields as $scope => $items) 
		{
			foreach($items as $field) 
			{
				if(!is_numeric($scope)) {
					$toMerge[] = $scope . '.' . $field;
					continue;
				}      
				
				$toMerge[] = $field;
			}
		}       
		
		$fields = $toMerge; 
		
		return $this->_fieldsReturn($type, $context, $fields, $schema);
	}

	protected function _fieldsReturn($type, $context, $fields, $schema) 
	{
		if($type == 'create' || $type == 'update') 
		{
			$data = $context->data();

			if($fields && is_array($fields) && is_int(key($fields)))
				$data = array_intersect_key($data, array_combine($fields, $fields));

			$method = "_{$type}Fields";         
			
			return $this->{$method}($data, $schema, $context);
		} 
		
		return empty($fields) ? '*' : join(', ', $fields);
	}

	public function limit($limit, $context) 
	{
		if(!$limit) return;
		if($offset = $context->offset() ?: '') 
			$offset .= ', ';   
			
		return "LIMIT {$offset}{$limit}";
	}

	public function joins(array $joins, $context) 
	{
		$result = null;

		foreach($joins as $model => $join) {
			if($result) $result .= ' ';
			$result .= $this->renderCommand('join', $join->export($this));
		}       
		
		return $result;
	}

	public function constraint($constraint, $context) 
	{
		if(!$constraint) return "";
		if(is_string($constraint)) return "ON {$constraint}";     
		
		$result = array();

		foreach($constraint as $field => $value) 
		{
			$field = $this->name($field);

			if(is_string($value)) {
				$result[] = $field . ' = ' . $this->name($value);
				continue;
			}
			if(!is_array($value)) continue;

			foreach($value as $operator => $val) 
			{
				if(isset($this->_operators[$operator])) {
					$val = $this->name($val);
					$result[] = "{$field} {$operator} {$val}";
				}
			}
		}   
		
		return 'ON ' . join(' AND ', $result);
	}

	public function order($order, $context) 
	{
		$direction = 'ASC';
		$model     = $context->model();

		if(is_string($order)) 
		{
			if(!$model::schema($order)) 
			{
				$match = '/\s+(A|DE)SC/i';
				
				return "ORDER BY {$order}" . (preg_match($match, $order) ? '' : " {$direction}");
			}   
			
			$order = array($order => $direction);
		}

		if(!is_array($order)) return;
		$result = array();

		foreach($order as $column => $dir) 
		{
			if(is_int($column)) {
				$column = $dir;
				$dir    = $direction;
			}
			$dir = in_array($dir, array('ASC', 'asc', 'DESC', 'desc')) ? $dir : $direction;

			if(!$model) {
				$result[] = "{$column} {$dir}";
				continue;
			}
			if($field = $model::schema($column)) {
				$name = $this->name($model::meta('name')) . '.' . $this->name($column);
				$result[] = "{$name} {$dir}";
			}
		}       
		
		$order = join(', ', $result);  
		
		return "ORDER BY {$order}";
	}

	public function group($group, $context = null) 
	{
		if(!$group) return null;

		return 'GROUP BY ' . join(', ', (array) $group);
	}

	public function comment($comment) 
	{
		return $comment ? "/* {$comment} */" : null;
	}

	public function alias($alias, $context) 
	{
		if(!$alias && ($model = $context->model())) 
  		$alias = $model::meta('name');

		return $alias ? "AS " . $this->name($alias) : null;
	}

	public function cast($entity, array $data, array $options = array()) 
	{
		return $data;
	}

	protected function _createFields($data, $schema, $context) 
	{
		$fields = $values = array();

		while(list($field, $value) = each($data)) {
			$fields[] = $this->name($field);
			$values[] = $this->value($value, isset($schema[$field]) ? $schema[$field] : array());
		}      
		
		$fields = join(', ', $fields);
		$values = join(', ', $values);
		
		return compact('fields', 'values');
	}

	protected function _updateFields($data, $schema, $context) 
	{
		$fields = array();

		while(list($field, $value) = each($data)) {
			$schema += array($field => array('default' => null));
			$fields[] = $this->name($field) . ' = ' . $this->value($value, $schema[$field]);
		}    
		
		return join(', ', $fields);
	}

	protected function _operator($key, $value, array $schema = array(), array $options = array()) 
	{
		$defaults = array('boolean' => 'AND');
		$options += $defaults;

		list($op, $value) = each($value);
		$config = $this->_operators[$op];
		$key    = $this->name($key);
		$values = array();

		if(!is_object($value)) 
		{
			foreach((array) $value as $val) {
				$values[] = $this->value($val, $schema);
			}
		}

		switch(true) 
		{
			case (isset($config['format'])):
				return $key . ' ' . String::insert($config['format'], $values);
			case (is_object($value) && isset($config['multiple'])):
				$op = $config['multiple'];
				$value = trim(rtrim($this->renderCommand($value), ';'));  
				
				return "{$key} {$op} ({$value})";
			case (count($values) > 1 && isset($config['multiple'])):
				$op = $config['multiple'];
				$values = join(', ', $values);   
				
				return "{$key} {$op} ({$values})";
			case (count($values) > 1):
				return join(" {$options['boolean']} ", array_map(
					function($v) use ($key, $op) { return "{$key} {$op} {$v}"; }, $values
				));
		}     
		
		return "{$key} {$op} {$values[0]}";
	}


	protected function _entityName($entity, array $options = array()) 
	{
		$defaults = array('quoted' => false);
		$options += $defaults;

		if(class_exists($entity, false) && method_exists($entity, 'meta'))
			$entity = $entity::meta('source');

		return $options['quoted'] ? $this->name($entity) : $entity;
	}

	protected function _introspectType($value) 
	{
		switch(true) 
		{
			case (is_bool($value)):
				return 'boolean';
			case (is_float($value) || preg_match('/^\d+\.\d+$/', $value)):
				return 'float';
			case (is_int($value) || preg_match('/^\d+$/', $value)):
				return 'integer';
			case (is_string($value) && strlen($value) <= $this->_columns['string']['length']):
				return 'string';
			default:
				return 'text';
		}
	}

	protected function _toBoolean($value) 
	{
		if(is_bool($value))
			return $value;
		if(is_int($value) || is_float($value))
			return ($value !== 0);
		if(is_string($value))
			return ($value == 't' || $value == 'T' || $value == 'true');

		return (boolean) $value;
	}

	protected function _toNativeBoolean($value) 
	{
		return $value ? 1 : 0;
	}
}