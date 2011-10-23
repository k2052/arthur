<?php

namespace arthur\tests\integration\net\http;

use arthur\net\http\Service;

class ServiceTest extends \arthur\test\Integration 
{
	public function testStreamGet() 
	{
		$service = new Service(array(
			'classes' => array('socket' => '\arthur\net\socket\Stream')
		));
		$service->head();

		$expected = array('code' => 200, 'message' => 'OK');
		$result   = $service->last->response->status;
		$this->assertEqual($expected, $result);
	}

	public function testContextGet() 
	{
		$service = new Service(array(
			'classes' => array('socket' => '\arthur\net\socket\Context')
		));
		$service->head();

		$expected = array('code' => 200, 'message' => 'OK');
		$result   = $service->last->response->status;
		$this->assertEqual($expected, $result);
	}

	public function testCurlGet() 
	{
		$service = new Service(array(
			'classes' => array('socket' => '\arthur\net\socket\Curl')
		));
		$service->head();

		$expected = array('code' => 200, 'message' => 'OK');
		$result   = $service->last->response->status;
		$this->assertEqual($expected, $result);
	}
}