<?php

namespace arthur\tests\integration\net;

use arthur\net\socket\Context;
use arthur\net\socket\Curl;
use arthur\net\socket\Stream;

class SocketTest extends \arthur\test\Integration 
{
	protected $_testConfig = array(
		'persistent' => false,
		'scheme'     => 'http',
		'host'       => 'www.lithify.me',
		'port'       => 80,
		'timeout'    => 1,
		'classes' => array(
			'request'  => 'arthur\net\http\Request',
			'response' => 'arthur\net\http\Response'
		)
	);

	public function skip() 
	{
		$message = "No internet connection established.";
		$this->skipIf(!$this->_hasNetwork($this->_testConfig), $message);
	}

	public function testContextAdapter() 
	{
		$socket = new Context($this->_testConfig);
		$this->assertTrue($socket->open());
		$response = $socket->send();
		$this->assertTrue($response instanceof \arthur\net\http\Response);

		$expected = 'www.lithify.me';
		$result   = $response->host;
		$this->assertEqual($expected, $result);

		$result = $response->body();
		$this->assertPattern("/<title[^>]*>.*Arthur.*<\/title>/im", (string) $result);
	}

	public function testCurlAdapter() 
	{
		$message = 'Your PHP installation was not compiled with curl support.';
		$this->skipIf(!function_exists('curl_init'), $message);

		$socket = new Curl($this->_testConfig);
		$this->assertTrue($socket->open());
		$response = $socket->send();
		$this->assertTrue($response instanceof \arthur\net\http\Response);

		$expected = 'www.lithify.me';
		$result   = $response->host;
		$this->assertEqual($expected, $result);

		$result = $response->body();
		$this->assertPattern("/<title[^>]*>.*Arthur.*<\/title>/im", (string) $result);
	}

	public function testStreamAdapter() 
	{
		$socket = new Stream($this->_testConfig);
		$this->assertTrue($socket->open());
		$response = $socket->send();
		$this->assertTrue($response instanceof \arthur\net\http\Response);

		$expected = 'www.lithify.me';
		$result   = $response->host;
		$this->assertEqual($expected, $result);

		$result = $response->body();
		$this->assertPattern("/<title[^>]*>.*Arthur.*<\/title>/im", (string) $result);
	}
}