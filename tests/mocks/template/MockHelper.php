<?php

namespace arthur\tests\mocks\template;

class MockHelper extends \arthur\template\Helper 
{
	protected $_strings = array('link' => '<a href="{:url}"{:options}>{:title}</a>');

	public function __get($property) 
	{
		return isset($this->{$property}) ? $this->{$property} : null;
	}

	public function testOptions($defaults, $options) 
	{
		return $this->_options($defaults, $options);
	}

	public function testAttributes($params, $method = null, array $options = array()) 
	{
		return $this->_attributes($params, $method, $options);
	}

	public function testRender($method, $string, $params, array $options = array()) 
	{
		return $this->_render($method, $string, $params, $options);
	}
}