<?php

namespace lithium\data\collection;

class DocumentSet extends \lithium\data\Collection 
{

	public function __set($name, $value = null) 
	{
		if(is_array($name) && !$value) 
		{
			foreach($name as $key => $value) {
				$this->__set($key, $value);
			} 
			
			return;
		}

		if(is_string($name) && strpos($name, '.')) 
		{
			$current = $this;
			$path    = explode('.', $name);
			$length  = count($path) - 1;

			for($i = 0; $i < $length; $i++) 
			{
				$key  = $path[$i];
				$next = $current->__get($key);

				if(!is_object($next) && ($model = $this->_model)) {
					$next = $model::connection()->cast($this, $next);
					$current->_data[$key] = $next;
				}
				$current = $next;
			}
			$current->__set(end($path), $value);
		}

		if(is_array($value))
			$value = $this->_relation('set', $name, $value);

		$this->_data[$name] = $value;
	}
	
	public function __isset($name) 
	{
		return isset($this->_data[$name]);
	}

	public function __unset($name) 
	{
		unset($this->_data[$name]);
	}

	public function set($values) 
	{
		foreach($values as $key => $val) {
			$this[$key] = $val;
		}
	}

	public function offsetGet($offset) 
	{
		$data  = null;
		$null  = null;
		$model = $this->_model;

		if(!isset($this->_data[$offset]) && !$data = $this->_populate(null, $offset))
			return $null;
		if(is_array($data = $this->_data[$offset]) && $model)
			$this->_data[$offset] = $model::connection()->cast($this, $data);
		if(isset($this->_data[$offset]))
			return $this->_data[$offset];

		return $null;
	}
	
	public function rewind() 
	{
		$data = parent::rewind() ?: $this->_populate();
		$key  = key($this->_data);

		if(is_object($data))
			return $data;

		if(isset($this->_data[$key]))
			return $this->offsetGet($key);
	}

	public function current() 
	{
		return $this->offsetGet(key($this->_data));
	}

	public function next() 
	{
		$prev         = key($this->_data);
		$this->_valid = !(next($this->_data) === false && key($this->_data) === null);
		$cur          = key($this->_data);

		if(!$this->_valid && $cur !== $prev && $cur !== null)
			$this->_valid = true;

		$this->_valid = $this->_valid ?: !is_null($this->_populate()); 
		
		return $this->_valid ? $this->offsetGet(key($this->_data)) : null;
	}

	public function export(array $options = array()) 
	{
		$map = function($doc) use ($options) 
		{
			return is_array($doc) ? $doc : $doc->export();
		};    
		
		return array_map($map, $this->_data);
	}

	protected function _populate($data = null, $key = null) 
	{
		if($this->closed() || !($model = $this->_model))
			return;
		$conn = $model::connection();

		if(($data = $data ?: $this->_result->next()) === null)
			return $this->close();

		$options = array('exists' => true, 'first' => true, 'pathKey' => $this->_pathKey);
		
		return $this->_data[] = $conn->cast($this, array($key => $data), $options);
	}

	protected function _relation($classType, $key, $data, $options = array()) 
	{
		$parent = $this;
		$model  = $this->_model;

		if(is_object($data) && $data instanceof Document) {
			$data->assignTo($this, compact('model', 'pathKey'));
			return $data;
		}
		$options += compact('model', 'data', 'parent');   
		
		return new $this->_classes[$classType]($options);
	}
}