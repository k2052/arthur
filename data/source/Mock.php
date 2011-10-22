<?php

namespace arthur\data\source;

class Mock extends \arthur\data\Source 
{
	public function connect() 
	{
		return true;
	}

	public function disconnect() 
	{
		return true;
	}

	public function sources($class = null) 
	{
		return array();
	}

	public function describe($entity, array $meta = array()) 
	{
		return array();
	}

	public function relationship($class, $type, $name, array $options = array()) 
	{
		return false;
	}

	public function create($query, array $options = array()) 
	{
		return false;
	}

	public function read($query, array $options = array()) 
	{
		return false;
	}

	public function update($query, array $options = array()) 
	{
		return false;
	}

	public function delete($query, array $options = array()) 
	{
		return false;
	}
}