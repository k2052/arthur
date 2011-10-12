<?php

namespace lithium\storage\cache\strategy;

class Serializer extends \lithium\core\Object 
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
