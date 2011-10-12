<?php

namespace lithium\data\source\database\adapter;

use lithium\data\model\QueryException;

class MySql extends \lithium\data\source\Database 
{
	protected $_classes = array(          
		'entity'       => 'lithium\data\entity\Record',
		'set'          => 'lithium\data\collection\RecordSet',
		'relationship' => 'lithium\data\model\Relationship',
		'result'       => 'lithium\data\source\database\adapter\my_sql\Result'
	);
	protected $_columns = array(
		'primary_key' => array('name' => 'NOT NULL AUTO_INCREMENT'),
		'string'      => array('name' => 'varchar', 'length' => 255),
		'text'        => array('name' => 'text'),
		'integer'     => array('name' => 'int', 'length' => 11, 'formatter' => 'intval'),
		'float'       => array('name' => 'float', 'formatter' => 'floatval'),
		'datetime'    => array('name' => 'datetime', 'format' => 'Y-m-d H:i:s', 'formatter' => 'date'),
		'timestamp'   => array(
			'name' => 'timestamp', 'format' => 'Y-m-d H:i:s', 'formatter' => 'date'
		),
		'time'    => array('name' => 'time', 'format' => 'H:i:s', 'formatter' => 'date'),
		'date'    => array('name' => 'date', 'format' => 'Y-m-d', 'formatter' => 'date'),
		'binary'  => array('name' => 'blob'),
		'boolean' => array('name' => 'tinyint', 'length' => 1)
	);     
	
	protected $_quotes = array('`', '`');
	protected $_useAlias = true;

	public function __construct(array $config = array()) 
	{
		$defaults = array('host' => 'localhost:3306', 'encoding' => null);
		parent::__construct($config + $defaults);
	}

	public static function enabled($feature = null) 
	{
		if(!$feature)
			return extension_loaded('mysql'); 
			
		$features = array(
			'arrays'        => false,
			'transactions'  => false,
			'booleans'      => true,
			'relationships' => true
		);      
		
		return isset($features[$feature]) ? $features[$feature] : null;
	}

	public function connect() 
	{
		$config             = $this->_config;
		$this->_isConnected = false;
		$host               = $config['host'];
                                                                 
		if(!$config['database']) return false;

		if(!$config['persistent'])
			$this->connection = mysql_connect($host, $config['login'], $config['password'], true);
		else
			$this->connection = mysql_pconnect($host, $config['login'], $config['password']);

		if(!$this->connection) return false;

		if(mysql_select_db($config['database'], $this->connection)) 
			$this->_isConnected = true;
		else
			return false;

		if($config['encoding'])
			$this->encoding($config['encoding']);

		$info           = mysql_get_server_info($this->connection);
		$this->_useAlias = (boolean) version_compare($info, "4.1", ">=");      
		
		return $this->_isConnected;
	}

	public function disconnect() 
	{
		if($this->_isConnected) {
			$this->_isConnected = !mysql_close($this->connection);    
			return !$this->_isConnected;
		}   
		
		return true;
	}

	public function sources($model = null) 
	{
		$_config = $this->_config;
		$params = compact('model');

		return $this->_filter(__METHOD__, $params, function($self, $params) use ($_config) 
		{
			$name = $self->name($_config['database']);

			if(!$result = $self->invokeMethod('_execute', array("SHOW TABLES FROM {$name};")))
				return null;

			$sources = array();

			while($data = $result->next()) {
				list($sources[]) = $data;
			}      
			
			return $sources;
		});
	}

	public function describe($entity, array $meta = array()) 
	{
		$params = compact('entity', 'meta');
		
		return $this->_filter(__METHOD__, $params, function($self, $params) 
		{
			extract($params);

			$name    = $self->invokeMethod('_entityName', array($entity, array('quoted' => true)));
			$columns = $self->read("DESCRIBE {$name}", array('return' => 'array', 'schema' => array(
				'field', 'type', 'null', 'key', 'default', 'extra'
			)));
			$fields = array();

			foreach($columns as $column) 
			{
				$match = $self->invokeMethod('_column', array($column['type']));

				$fields[$column['field']] = $match + array(
					'null'     => ($column['null'] == 'YES' ? true : false),
					'default'  => $column['default']
				);
			}        
			
			return $fields;
		});
	}
                                            
	public function encoding($encoding = null) 
	{
		$encodingMap = array('UTF-8' => 'utf8');

		if(empty($encoding)) {
			$encoding = mysql_client_encoding($this->connection);
			return ($key = array_search($encoding, $encodingMap)) ? $key : $encoding;
		}
		$encoding = isset($encodingMap[$encoding]) ? $encodingMap[$encoding] : $encoding;
		
		return mysql_set_charset($encoding, $this->connection);
	}

	public function value($value, array $schema = array()) 
	{
		if(($result = parent::value($value, $schema)) !== null)
			return $result;
			
		return "'" . mysql_real_escape_string((string) $value, $this->connection) . "'";
	}
      
	public function schema($query, $resource = null, $context = null) 
	{
		if(is_object($query))
			return parent::schema($query, $resource, $context);

		$result = array();
		$count  = mysql_num_fields($resource->resource());

		for($i = 0; $i < $count; $i++) {
			$result[] = mysql_field_name($resource->resource(), $i);
		}                               
		
		return $result;
	}

	public function error() 
	{
		if(mysql_error($this->connection))
			return array(mysql_errno($this->connection), mysql_error($this->connection));

		return null;
	}

	public function alias($alias, $context) 
	{
		if($context->type() == 'update' || $context->type() == 'delete')
			return;
			
		return parent::alias($alias, $context);
	}
            
	public function conditions($conditions, $context, array $options = array()) 
	{
		return parent::conditions($conditions, $context, $options);
	}

	protected function _execute($sql, array $options = array()) 
	{
		$defaults = array('buffered' => true);
		$options += $defaults;
		mysql_select_db($this->_config['database'], $this->connection);

		return $this->_filter(__METHOD__, compact('sql', 'options'), function($self, $params) 
		{
			$sql     = $params['sql'];
			$options = $params['options'];

			$func     = ($options['buffered']) ? 'mysql_query' : 'mysql_unbuffered_query';
			$resource = $func($sql, $self->connection);

			if($resource === true) return true;
			if(is_resource($resource))
				return $self->invokeMethod('_instance', array('result', compact('resource')));

			list($code, $error) = $self->error();
			throw new QueryException("{$sql}: {$error}", $code);
		});
	}

	protected function _results($results) 
	{
		$numFields = mysql_num_fields($results);
		$index = $j = 0;

		while($j < $numFields) 
		{
			$column = mysql_fetch_field($results, $j);
			$name   = $column->name;
			$table  = $column->table;
			$this->map[$index++] = empty($table) ? array(0, $name) : array($table, $name);
			$j++;
		}
	}

	protected function _insertId($query) 
	{
		$resource = $this->_execute('SELECT LAST_INSERT_ID() AS insertID');
		list($id) = $resource->next();    
		
		return ($id && $id !== '0') ? $id : null;
	}
        
	protected function _column($real) 
	{
		if(is_array($real))
			return $real['type'] . (isset($real['length']) ? "({$real['length']})" : '');

		if(!preg_match('/(?P<type>\w+)(?:\((?P<length>[\d,]+)\))?/', $real, $column))
			return $real;
			
		$column = array_intersect_key($column, array('type' => null, 'length' => null));

		if(isset($column['length']) && $column['length']) 
		{
			$length = explode(',', $column['length']) + array(null, null);
			$column['length'] = $length[0] ? intval($length[0]) : null;
			$length[1] ? $column['precision'] = intval($length[1]) : null;
		}

		switch(true) 
		{
			case in_array($column['type'], array('date', 'time', 'datetime', 'timestamp')):
				return $column;
			case ($column['type'] == 'tinyint' && $column['length'] == '1'):
			case ($column['type'] == 'boolean'):
				return array('type' => 'boolean');
			break;
			case (strpos($column['type'], 'int') !== false):
				$column['type'] = 'integer';
			break;
			case (strpos($column['type'], 'char') !== false || $column['type'] == 'tinytext'):
				$column['type'] = 'string';
			break;
			case (strpos($column['type'], 'text') !== false):
				$column['type'] = 'text';
			break;
			case (strpos($column['type'], 'blob') !== false || $column['type'] == 'binary'):
				$column['type'] = 'binary';
			break;
			case preg_match('/float|double|decimal/', $column['type']):
				$column['type'] = 'float';
			break;
			default:
				$column['type'] = 'text';
			break;
		}

		return $column;
	}
}