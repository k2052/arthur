<?php

namespace arthur\storage\cache\strategy;

class Base64 extends \arthur\core\Object 
{
	public function write($data) 
	{
		return base64_encode($data);
	}

	public function read($data) 
	{
		return base64_decode($data);
	}
}
