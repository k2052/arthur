<?php

namespace arthur\tests\cases\template\helper;

use arthur\template\helper\Security;
use arthur\tests\mocks\template\helper\MockFormRenderer;

class SecurityTest extends \arthur\test\Unit 
{
	public $subject;
	public $context;

	public static function key($token) 
	{
		return 'WORKING';
	}

	public static function hash($token) 
	{
		return $token;
	}

	public function setUp() 
	{
		$this->context = new MockFormRenderer(compact('request'));
		$this->subject = new Security(array('context' => $this->context));
	}

	public function testRequestToken() 
	{
		$result = explode(' ', $this->subject->requestToken());

		$this->assertEqual('<input', $result[0]);
		$this->assertEqual('type="hidden"', $result[1]);
		$this->assertEqual('name="security[token]"', $result[2]);
		$this->assertEqual('/>', $result[4]);

		$result = explode('=', $result[3]);
		$this->assertEqual('value', $result[0]);

		$result = trim($result[1], '"');
		$this->assertPattern('/^\$\d\w\$\d{2}\$[A-Za-z0-9\.\/]{53}$/', $result);
	}

	public function testConstruct() 
	{
		$this->subject = new Security(array('context' => $this->context, 'classes' => array(
			'password'     => __CLASS__,
			'requestToken' => __CLASS__
		)));
		$this->assertPattern('/value="WORKING"/', $this->subject->requestToken());
	}
}