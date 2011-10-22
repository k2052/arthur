<?php

namespace arthur\data\collection;

class RecordSet extends \arthur\data\Collection 
{
	protected $_index = array();
	protected $_pointer = 0;
	protected $_columns = array();

	protected function _init() 
	{
		parent::_init();

		if($this->_result)
			$this->_columns = $this->_columnMap();
		if($this->_data && !$this->_index) {
			$this->_index = array_keys($this->_data);
			$this->_data = array_values($this->_data);
		}
	}

	public function offsetExists($offset) 
	{
		if(in_array($offset, $this->_index)) 
			return true;                   
			
		return parent::offsetExists($offset);
	}

	public function offsetGet($offset) 
	{
		if($offset !== null && in_array($offset, $this->_index)) 
			return $this->_data[array_search($offset, $this->_index)];
		if($this->closed()) 
			return null;

		$model = $this->_model;

		while($record = $this->_populate(null, $offset)) 
		{
			$key    = $model::key($record);
			$keySet = $offset == $key || (!$key && in_array($offset, $this->_index));     
			
			if(!is_null($offset) && $keySet)
				return $record;
		}
		$this->close();
	}

	public function offsetSet($offset, $data) 
	{
		return $this->_populate($data, $offset);
	}

	public function offsetUnset($offset) 
	{
		unset($this->_index[$index = array_search($offset, $this->_index)]);
		unset($this->_data[$index]);
	}

	public function rewind() 
	{
		$this->_pointer = 0;
		reset($this->_index);

		if($record = parent::rewind())
			return $record;

		return empty($this->_data) ? null : $this->_data[$this->_pointer];
	}
	
	public function current() 
	{
		return $this->_data[$this->_pointer];
	}

	public function key($full = false) 
	{
		$key = $this->_index[$this->_pointer];  
		
		return (is_array($key) && !$full) ? reset($key) : $key;
	}


	public function next() 
	{
		$this->_valid = (next($this->_data) !== false && next($this->_index) !== false);

		if(!$this->_valid)
			$this->_valid = !is_null($this->_populate());
		$return = null;

		if($this->_valid) {
			$this->_pointer++;
			$return = $this->current();
		}  
		
		return $return;
	}

	public function prev() 
	{
		$this->_valid = (prev($this->_data) !== false && prev($this->_index) !== false);

		$return = null;

		if($this->_valid) {
			$this->_pointer--;
			$return = $this->current();
		}   
		
		return $return;
	}

	public function to($format, array $options = array()) 
	{
		$defaults = array('indexed' => true);
		$options += $defaults;

		$result = null;
		$this->offsetGet(null);

		switch($format) 
		{
			case 'array':
				$result = array_map(function($r) { return $r->to('array'); }, $this->_data);
				
				if(!(is_scalar(current($this->_index)) && $options['indexed']))
					break;
				$indexAndResult = ($this->_index && $result);
				$result =  $indexAndResult ? array_combine($this->_index, $result) : array();
			break;
			default:
				$result = parent::to($format, $options);
			break;
		}      
		
		return $result;
	}

	public function each($filter) 
	{
		$this->offsetGet(null);
		
		return parent::each($filter);
	}

	public function find($filter, array $options = array()) 
	{
		$this->offsetGet(null);      
		
		return parent::find($filter, $options);
	}

	public function map($filter, array $options = array()) 
	{
		$this->offsetGet(null);   
		
		return parent::map($filter, $options);
	}

	protected function _populate($data = null, $key = null) 
	{
		if($this->closed() && !$data || !($model = $this->_model))
			return;

		if(!($data = $data ?: $this->_result->next()))
			return $this->close();
		$record = is_object($data) ? $data : $this->_mapRecord($data);
		$key    = $model::key($record);

		if(!$key) 
			$key = count($this->_data);

		if(is_array($key))
			$key = count($key) === 1 ? reset($key) : $key;
		if(in_array($key, $this->_index)) 
		{
			$index = array_search($key, $this->_index);
			$this->_data[$index] = $record;
			return $this->_data[$index];
		}
		$this->_data[]  = $record;
		$this->_index[] = $key;  
		
		return $record;
	}

	protected function _mapRecord($data) 
	{
		$options       = array('exists' => true);
		$relationships = array();
		$primary       = $this->_model;
		$conn          = $primary::connection();

		if(!$this->_query)
			return $conn->item($primary, $data, $options + compact('relationships'));

		$dataMap = array();
		$relMap  = $this->_query->relationships();
		$main    = null;

		do 
		{
			$offset = 0;

			foreach($this->_columns as $name => $fields) 
			{
				$fieldCount = count($fields);
				$record     = array_combine($fields, array_slice($data, $offset, $fieldCount));
				$offset    += $fieldCount;

				if($name === 0) 
				{
					if($main && $main != $record) {
						$this->_result->prev();
						break 2;
					}
					$main = $record;
					continue;
				}

				if($relMap[$name]['type'] != 'hasMany') {
					$dataMap[$name] = $record;
					continue;
				}
				$dataMap[$name][] = $record;
			}
		} while($data = $this->_result->next());

		foreach($dataMap as $name => $rel) 
		{
			$field    = $relMap[$name]['fieldName'];
			$relModel = $relMap[$name]['model'];

			if($relMap[$name]['type'] == 'hasMany') 
			{
				foreach($rel as &$data) {
					$data = $conn->item($relModel, $data, $options);
				}
				$opts = array('class' => 'set');
				$relationships[$field] = $conn->item($relModel, $rel, $options + $opts);
				continue;
			}
			$relationships[$field] = $conn->item($relModel, $rel, $options);
		}      
		
		return $conn->item($primary, $main, $options + compact('relationships'));
	}

	protected function _columnMap() 
	{
		if($this->_query && $map = $this->_query->map()) 
		{
			if(isset($map[$this->_query->alias()])) {
				$map = array($map[$this->_query->alias()]) + $map;
				unset($map[$this->_query->alias()]);
			} 
			else
				$map = array(array_shift($map)) + $map;
				
			return $map;
		}
		if(!($model = $this->_model))
			return array();
		if(!is_object($this->_query) || !$this->_query->join()) 
		{
			$map = $model::connection()->schema($this->_query, $this->_result, $this);    
			
			return array_values($map);
		}

		$model = $this->_model;
		$map   = $model::connection()->schema($this->_query, $this->_result, $this);
		$map   = array($map[$this->_query->alias()]) + $map;
		unset($map[$this->_query->alias()]);

		return $map;
	}
}