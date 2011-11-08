<?php

namespace arthur\tests\cases\analysis\logger\adapter;

use arthur\core\Libraries;
use arthur\util\collection\Filters;
use arthur\analysis\logger\adapter\File;

class FileTest extends \arthur\test\Unit 
{
	public $subject;

	public function skip() 
	{
		$path = realpath(Libraries::get(true, 'resources') . '/tmp/logs');
		$this->skipIf(!is_writable($path), "Path `{$path}` is not writable.");
	}

	public function setUp() 
	{
		$this->path = Libraries::get(true, 'resources') . '/tmp/logs';
		$this->tearDown();
	}

	public function tearDown() 
	{
		if(file_exists("{$this->path}/debug.log"))
			unlink("{$this->path}/debug.log");
	}

	public function testWriting() 
	{
		$this->subject = new File(array('path' => $this->path)); 
		
		$priority  = 'debug';
		$message   = 'This is a debug message';
		$function  = $this->subject->write($priority, $message);
		$now       = date('Y-m-d H:i:s');
		$function('arthur\analysis\Logger', compact('priority', 'message'), new Filters());

		$log = file_get_contents("{$this->path}/debug.log");
		$this->assertEqual("{$now} This is a debug message\n", $log);
	}

	public function testWithoutTimestamp() 
	{
		$this->subject = new File(array(
			'path' => $this->path, 'timestamp' => false, 'format' => "{:message}\n"
		));
		$priority = 'debug';
		$message  = 'This is a debug message';
		$function = $this->subject->write($priority, $message);
		$now      = date('Y-m-d H:i:s');
		$function('arthur\analysis\Logger', compact('priority', 'message'), new Filters());

		$log = file_get_contents("{$this->path}/debug.log");
		$this->assertEqual("This is a debug message\n", $log);
	}
}