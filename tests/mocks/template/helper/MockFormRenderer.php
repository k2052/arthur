<?php

namespace arthur\tests\mocks\template\helper;

use arthur\action\Request;

class MockFormRenderer extends \arthur\template\view\Renderer 
{
	public function request() 
	{
		if(empty($this->_request)) {
			$this->_request = new Request();
			$this->_request->params += array('controller' => 'posts', 'action' => 'add');
		}    
		
		return $this->_request;
	}

	public function render($template, $data = array(), array $options = array()) { }
}