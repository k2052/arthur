<?php

namespace arthur\storage\cache\strategy;

class Json extends \arthur\core\Object
{
	public function write($data) 
	{
		return json_encode($data);
	}

	public function read($data) 
	{
		return json_decode($data, true);
	}
}