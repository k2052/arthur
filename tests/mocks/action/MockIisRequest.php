<?php

namespace arthur\tests\mocks\action;

class MockIisRequest extends \arthur\action\Request 
{
	protected function _init() 
	{
		parent::_init();
		$this->_env = array(
			'PLATFORM'            => 'IIS',
			'SCRIPT_NAME'         => '\index.php',
			'SCRIPT_FILENAME'     => false,
			'DOCUMENT_ROOT'       => false,
			'PATH_TRANSLATED'     => '\arthur\app\webroot\index.php',
			'HTTP_PC_REMOTE_ADDR' => '123.456.789.000'
		);
	}
}