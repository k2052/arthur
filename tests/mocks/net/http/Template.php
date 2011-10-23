<?php

namespace arthur\tests\mocks\net\http;

class Template extends \arthur\core\Object 
{
	public function __construct(array $config = array()) 
	{
		$config['response']->headers('Custom', 'Value');
	}

	public function render() { }
}