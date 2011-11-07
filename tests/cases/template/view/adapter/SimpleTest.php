<?php

namespace arthur\tests\cases\template\view\adapter;

use arthur\template\view\adapter\Simple;
use arthur\tests\mocks\util\MockStringObject;

class SimpleTest extends \arthur\test\Unit 
{
	public $subject = null;

	public function setUp() 
	{
		$this->subject = new Simple();
	}

	public function testBasicRender() 
	{
		$result   = $this->subject->template('layout', array('layout' => '{:content}'));
		$expected = '{:content}';
		$this->assertEqual($expected, $result);

		$message = new MockStringObject();
		$message->message = 'Arthur is about to rock you.';

		$result = $this->subject->render('Hello {:name}! {:message}', compact('message') + array(
			'name' => 'World'
		));
		$expected = 'Hello World! Arthur is about to rock you.';
		$this->assertEqual($expected, $result);
	}
}