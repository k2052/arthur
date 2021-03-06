<?php

namespace arthur\data\source\database\adapter\my_sql;

class Result extends \arthur\data\source\database\Result 
{
	public function prev() 
	{
		if($this->_current = $this->_prev()) {
			$this->_iterator--;
			return $this->_current;
		}
	}

	protected function _prev() 
	{
		if($this->_resource && $this->_iterator) {
			if(mysql_data_seek($this->_resource, $this->_iterator -1))
				return mysql_fetch_row($this->_resource);
		}
	}

	protected function _next() 
	{
		if($this->_resource) 
		{
			$inRange = $this->_iterator < mysql_num_rows($this->_resource);
			if($inRange && mysql_data_seek($this->_resource, $this->_iterator))
				return mysql_fetch_row($this->_resource);
		}
	}

	protected function _close() 
	{
		if($this->_resource)
			mysql_free_result($this->_resource);
	}
}