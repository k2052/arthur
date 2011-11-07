<?php

namespace arthur\tests\cases\security;

use arthur\security\Auth;
use arthur\storage\Session;

class AuthTest extends \arthur\test\Unit 
{
	public function setUp() 
	{
		Session::config(array(
			'test' => array('adapter' => 'Memory')
		));

		Auth::config(array(
			'test' => array(
				'adapter' => 'arthur\tests\mocks\security\auth\adapter\MockAuthAdapter'
			)
		));
	}

	public function testBasicAuthCheck() 
	{
		$this->assertFalse(Auth::check('test'));
		$user = array('user' => 'bob');

		$result = Auth::check('test', $user, array('success' => true));
		$this->assertEqual($user, $result);

		$result = Session::read('test');
		$this->assertEqual($user, $result);

		$result = Auth::check('test');
		$this->assertEqual($user, $result);
	}

	public function testAuthLogout() 
	{
		$user = array('user' => 'bob');

		$result = Auth::check('test', $user, array('success' => true));
		$this->assertEqual($user, $result);

		$result = Auth::check('test');
		$this->assertEqual($user, $result);

		Auth::clear('test');
		$this->assertFalse(Auth::check('test'));
	}

	public function testManualSessionInitialization()
	{
		$this->assertFalse(Auth::check('test'));
		$user = array('id' => 13, 'user' => 'bob');

		$this->assertTrue(Auth::set('test', $user));

		$result = Auth::check('test');
		$this->assertEqual($user, $result);
	}

	public function testManualSessionFail() 
	{
		$this->assertFalse(Auth::check('test'));
		$user = array('id' => 13, 'user' => 'bob');

		$this->assertFalse(Auth::set('test', $user, array('fail' => true)));
		$this->assertFalse(Auth::check('test'));
	}

	public function testNoConfigurations() 
	{
		Auth::reset();
		$this->assertIdentical(array(), Auth::config());
		$this->expectException("Configuration `user` has not been defined.");
		Auth::check('user');
	}
}