<?php

namespace arthur\tests\cases\net\http;

use arthur\net\http\Message;

class MessageTest extends \arthur\test\Unit 
{
	public $request = null;

	public function setUp() 
	{
		$this->message = new Message();
	}

	public function testHeaderKey() 
	{
		$expected = array(
			'Host: localhost:80'
		);
		$result = $this->message->headers('Host: localhost:80');
		$this->assertEqual($expected, $result);

		$expected = 'localhost:80';
		$result   = $this->message->headers('Host');
		$this->assertEqual($expected, $result);

		$result = $this->message->headers('Host', false);
		$this->assertFalse($result);
	}

	public function testHeaderKeyValue() 
	{
		$expected = array(
			'Connection: Close'
		);
		$result = $this->message->headers('Connection', 'Close');
		$this->assertEqual($expected, $result);
	}

	public function testHeaderArrayValue() 
	{
		$expected = array('User-Agent: Mozilla/5.0');
		$result   = $this->message->headers(array('User-Agent: Mozilla/5.0'));
		$this->assertEqual($expected, $result);
	}

	public function testHeaderArrayKeyValue() 
	{
		$expected = array(
			'Cache-Control: no-cache'
		);
		$result = $this->message->headers(array('Cache-Control' => 'no-cache'));
		$this->assertEqual($expected, $result);
	}

	public function testType() 
	{
		$this->assertEqual('json', $this->message->type("json"));
		$this->assertEqual('json', $this->message->type());

		$expected = 'json';
		$result   = $this->message->type("application/json; charset=UTF-8");
		$this->assertEqual($expected, $result);
	}
}