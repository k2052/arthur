<?php

namespace lithium\data\source\database\adapter;

use SQLite3 as SQLite;
use SQLite3Result;
use lithium\data\model\QueryException;


class Sqlite3 extends \lithium\data\source\Database 
{
	protected $_classes = array(
		'entity'       => 'lithium\data\entity\Record',
		'set'          => 'lithium\data\collection\RecordSet',
		'relationship' => 'lithium\data\model\Relationship',
		'result'       => 'lithium\data\source\database\adapter\sqlite3\Result'
	);

	protected $_quotes = array('"', '"');

	protected $_columns = array(
		'primary_key' => array('name' => 'integer primary key'),
		'string'      => array('name' => 'varchar', 'limit' => '255'),
		'text'        => array('name' => 'text'),
		'integer'     => array('name' => 'integer', 'limit' => 11, 'formatter' => 'intval'),
		'float'       => array('name' => 'float', 'formatter' => 'floatval'),
		'datetime'    => array('name' => 'datetime', 'format' => 'Y-m-d H:i:s', 'formatter' => 'date'),
		'timestamp'   => array(
			'name' => 'timestamp', 'format' => 'Y-m-d H:i:s', 'formatter' => 'date'
		),
		'time'    => array('name' => 'time', 'format' => 'H:i:s', 'formatter' => 'date'),
		'date'    => array('name' => 'date', 'format' => 'Y-m-d', 'formatter' => 'date'),
		'binary'  => array('name' => 'blob'),
		'boolean' => array('name' => 'boolean')
	);

	protected $_regex = array(
		'column' => '(?P<type>[^(]+)(?:\((?P<length>[^)]+)\))?'
	);

	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'database' => '',
			'flags'    => SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE,
			'key'      => null
		);           
		
		parent::__construct($config + $defaults);
	}

	public static function enabled($feature = null) 
	{
		if(!$feature)
			return extension_loaded('sqlite3');

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
		$this->connection = new SQLite(
			$this->_config['database'], $this->_config['flags'], $this->_config['key']
		);   
		
		return $this->_isConnected = (boolean) $this->connection;
	}

	public function disconnect() 
	{
		return !$this->_isConnected || !($this->_isConnected = !$this->connection->close());
	}

	public function sources($model = null) 
	{
		$config = $this->_config;

		return $this->_filter(__METHOD__, compact('model'), function($self, $params) use ($config) 
		{
			$sql     = "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;";
			$result  = $self->invokeMethod('_execute', array($sql));
			$sources = array();

			while($data = $result->next()) {
				$sources[] = reset($data);
			}     
			
			return $sources;
		});
	}

	public function describe($entity, array $meta = array()) 
	{
		$params = compact('entity', 'meta');
		$regex  = &$this->_regex;              
		
		return $this->_filter(__METHOD__, $params, function($self, $params) use ($regex) 
		{
			extract($params);

			$name    = $self->invokeMethod('_entityName', array($entity, array('quoted' => true)));
			$columns = $self->read("PRAGMA table_info({$name})", array('return' => 'array'));
			$fields  = array();

			foreach($columns as $column) 
			{
				preg_match("/{$regex['column']}/", $column['type'], $matches);

				$fields[$column['name']] = array(
					'type'    => isset($matches['type']) ? $matches['type'] : null,
					'length'  => isset($matches['length']) ? $matches['length'] : null,
					'null'    => $column['notnull'] == 1,
					'default' => $column['dflt_value']
				);
			}   
			
			return $fields;
		});
	}

	protected function _insertId($query) 
	{
		return $this->connection->lastInsertRowID();
	}

	public function encoding($encoding = null) 
	{
		$encodingMap = array('UTF-8' => 'utf8');

		if(!$encoding) {
			$encoding = $this->connection->querySingle('PRAGMA encoding');
			return ($key = array_search($encoding, $encodingMap)) ? $key : $encoding;
		}    
		
		$encoding = isset($encodingMap[$encoding]) ? $encodingMap[$encoding] : $encoding;
		$this->connection->exec("PRAGMA encoding = \"{$encoding}\"");  
		
		return $this->connection->querySingle("PRAGMA encoding");
	}        
	
	public function value($value, array $schema = array()) 
	{
		if(is_array($value))
			return parent::value($value, $schema);

		return "'" . $this->connection->escapeString($value) . "'";
	}

	public function schema($query, $resource = null, $context = null) 
	{
		if(is_object($query))
			return parent::schema($query, $resource, $context);

		$result = array();
		$count  = $resource->numColumns();

		for($i = 0; $i < $count; $i++) {
			$result[] = $resource->columnName($i);
		}   
		
		return $result;
	}

	public function error() 
	{
		if($this->connection->lastErrorMsg())
			return array($this->connection->lastErrorCode(), $this->connection->lastErrorMsg());
	}

	protected function _execute($sql, array $options = array()) 
	{
		$params = compact('sql', 'options');
		$conn  =& $this->connection;

		return $this->_filter(__METHOD__, $params, function($self, $params) use (&$conn) 
		{
			extract($params);

			if(!($resource = $conn->query($sql)) instanceof SQLite3Result) {
				list($code, $error) = $self->error();
				throw new QueryException("{$sql}: {$error}", $code);
			}   
			
			return $self->invokeMethod('_instance', array('result', compact('resource')));
		});
	}

	protected function _column($real) 
	{
		if(is_array($real))
			return $real['type'] . (isset($real['length']) ? "({$real['length']})" : '');

		if(!preg_match("/{$this->_regex['column']}/", $real, $column))
			return $real;

		$column = array_intersect_key($column, array('type' => null, 'length' => null));

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