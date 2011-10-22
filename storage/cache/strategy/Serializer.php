<?php

namespace arthur\storage\cache\strategy;

class Serializer extends \arthur\core\Object 
{

	public function write($data) 
	{
		return serialize($data);
	}

	public function read($data) 
	{
		return unserialize($data);
	}
}
