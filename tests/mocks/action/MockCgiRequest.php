<?php

namespace arthur\tests\mocks\action;

class MockCgiRequest extends \arthur\action\Request 
{
	protected function _init() 
	{
		parent::_init();
		$this->_env = array(
			'PLATFORM'        => 'CGI',
			'SCRIPT_FILENAME' => false,
			'DOCUMENT_ROOT'   => false,
			'SCRIPT_URL'      => '/arthur/app/webroot/index.php'
		);
	}
}
