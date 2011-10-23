<?php

namespace arthur\tests\mocks\util;

class MockStringObject extends \arthur\template\view\Renderer 
{
	public $message = 'custom object';

	public function render($template, $data = array(), array $options = array()) { }

	public function __toString() 
	{
		return $this->message;
	}
}