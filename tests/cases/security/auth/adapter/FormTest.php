<?php

namespace arthur\tests\cases\security\auth\adapter;

use arthur\security\Password;
use arthur\data\entity\Record;
use arthur\security\auth\adapter\Form;

class FormTest extends \arthur\test\Unit 
{
	public static function first(array $options = array()) 
	{
		if(!$options['conditions']) return null;   
		
		return new Record(array('data' => $options['conditions']));
	}

	public static function validatorTest(array $options = array()) 
	{
		return new Record(array('data' => array(
			'username' => 'Bob',
			'password' => Password::hash('s3cure'),
			'group'    => 'editors'
		)));
	}

	public function testLogin() 
	{
		$subject = new Form(array(
			'model'      => __CLASS__,
			'fields'     => array('username'),
			'validators' => array('password' => false)
		));

		$request = (object) array('data' => array(
			'username' => 'Person'
		));

		$result   = $subject->check($request);
		$expected = array('username' => 'Person');
		$this->assertEqual($expected, $result);

		$subject = new Form(array(
			'model'      => __CLASS__,
			'fields'     => array(),
			'validators' => array('password' => false)
		));

		$request = (object) array('data' => array());
		$this->assertFalse($subject->check($request));
	}

	public function testLoginWithFilters() 
	{
		$subject = new Form(array(
			'model'      => __CLASS__,
			'fields'     => array('username'),
			'filters'    => array('username' => 'sha1'),
			'validators' => array('password' => false)
		));

		$request = (object) array('data' => array('username' => 'Person'));

		$expected = array('username' => sha1('Person'));
		$result   = $subject->check($request);
		$this->assertEqual($expected, $result);

		$subject = new Form(array(
			'model'      => __CLASS__,
			'fields'     => array('username', 'date'),
			'filters'    => array(
				'username' => false,
				'date'     => function($date) 
				{
					return "{$date['year']}-{$date['month']}-{$date['day']}";
				}
			),
			'validators' => array('password' => false)
		));

		$request = (object) array('data' => array(
			'username' => 'bob',
			'date'     => array(
				'year' => '2011', 'month' => '06', 'day' => '29'
			)
		));

		$expected = array('username' => 'bob', 'date' => '2011-06-29');
		$result   = $subject->check($request);
		$this->assertEqual($expected, $result);
	}

	public function testUncallableFilter() 
	{
		$subject = new Form(array(
			'model'   => __CLASS__,
			'filters' => array(
				'username' => true
			)
		));

		$request = (object) array('data' => array('username' => 'Test'));

		$this->expectException('Authentication filter for `username` is not callable.');
		$subject->check($request);
	}

	public function testGenericFilter() 
	{
		$subject = new Form(array(
			'model'   => __CLASS__,
			'fields'  => array('username', 'password', 'group', 'secret'),
			'filters' => array(
				function($form) 
				{
					unset($form['secret']);
					return $form;
				}
			),
			'validators' => array('password' => false)
		));

		$request = (object) array('data' => array(
			'username' => 'bob',
			'group'    => 'editors',
			'secret'   => 'value',
			'password' => 'foo!'
		));

		$result   = $subject->check($request);
		$expected = array('username' => 'bob', 'group' => 'editors', 'password' => 'foo!');
		$this->assertEqual($expected, $result);

		$subject = new Form(array(
			'model'      => __CLASS__,
			'fields'     => array('username', 'password', 'group', 'secret'),
			'validators' => array('password' => false)
		));

		$request = (object) array('data' => array(
			'username' => 'bob',
			'group'    => 'editors',
			'secret'   => 'value',
			'password' => 'foo!'
		));

		$result   = $subject->check($request);
		$expected = array(
			'username' => 'bob', 'group' => 'editors', 'password' => 'foo!', 'secret' => 'value'
		);
		$this->assertEqual($expected, $result);
	}

	public function testUncallableGenericFilter() 
	{
		$subject = new Form(array(
			'model'   => __CLASS__,
			'filters' => array(
				true
			)
		));

		$request = (object) array('data' => array('username' => 'Test'));

		$this->expectException('Authentication filter is not callable.');
		$subject->check($request);
	}

	public function testLoginWithArray() 
	{
		$subject = new Form(array(
			'model'      => __CLASS__,
			'validators' => array('password' => false)
		));

		$request = (object) array('data' => array(
			'username' => array('!=' => ''), 'password' => ''
		));

		$result = $subject->check($request);
		$this->assertEqual('Array', $result['username']);
	}

	public function testSetPassthru() 
	{
		$subject = new Form(array('model' => __CLASS__));
		$user    = array('id' => 5, 'name' => 'bob');

		$result = $subject->set($user);
		$this->assertIdentical($user, $result);
	}

	public function testMixedFieldMapping() 
	{
		$subject = new Form(array(
			'model'      => __CLASS__,
			'fields'     => array('username' => 'name', 'group'),
			'validators' => array()
		));

		$request = (object) array('data' => array(
			'username' => 'Bob', 'group' => 'editors'
		));

		$expected = array('name' => 'Bob', 'group' => 'editors');
		$this->assertEqual($expected, $subject->check($request));
	}

	public function testDefaultValidator() 
	{
		$subject = new Form(array(
			'model'      => __CLASS__,
			'fields'     => array('username', 'password', 'group'),
			'validators' => array('password' => false)
		));

		$request = (object) array('data' => array(
			'username' => 'Bob', 'password' => 's3cure', 'group' => 'editors'
		));

		$result   = $subject->check($request);
		$expected = array('username' => 'Bob', 'group' => 'editors', 'password' => 's3cure');
		$this->assertEqual($expected, $result);
	}

	public function testParameterValidators() 
	{
		$subject = new Form(array(
			'model'      => __CLASS__,
			'query'      => 'validatorTest',
			'validators' => array(
				'password' => function($form, $data) 
				{
					return Password::check($form, $data);
				},
				'group' => function($form) 
				{
					return $form === 'editors';
				}
			)
		));

		$request = (object) array('data' => array(
			'username' => 'Bob', 'password' => 's3cure', 'group' => 'editors'
		));

		$result = $subject->check($request);
		$this->assertEqual(array_keys($request->data), array_keys($result));

		$this->assertEqual('Bob', $result['username']);
		$this->assertEqual('editors', $result['group']);
		$this->assertTrue(Password::check('s3cure', $result['password']));
	}

	public function testOmitValidatedParams() 
	{
		$subject = new Form(array(
			'model'      => __CLASS__,
			'validators' => array(
				'password' => function($form, $data) { return true; },
				'group'    => function($form) { return true; }
			)
		));

		$request = (object) array('data' => array(
			'username' => 'Bob', 'password' => 's3cure', 'group' => 'editors'
		));

		$result = $subject->check($request);
		$this->assertEqual(array('username' => 'Bob'), $result);
	}

	public function testParameterValidatorsFail() 
	{
		$subject = new Form(array(
			'model'      => __CLASS__,
			'validators' => array(
				'password' => function($form, $data) { return false; }
			)
		));

		$request = (object) array('data' => array(
			'username' => 'Bob', 'password' => 's3cure', 'group' => 'editors'
		));

		$result = $subject->check($request);
		$this->assertFalse($result);
	}

	public function testUncallableValidator() 
	{
		$subject = new Form(array(
			'model'      => __CLASS__,
			'validators' => array('password' => true)
		));

		$request = (object) array('data' => array('username' => 'Bob'));

		$this->expectException('Authentication validator for `password` is not callable.');
		$subject->check($request);
	}

	public function testGenericValidator() 
	{
		$self = $this;
		$subject = new Form(array(
			'model'      => __CLASS__,
			'query'      => 'validatorTest',
			'validators' => array(
				function($data, $user) use ($self) {
					return true;
				}
			)
		));

		$request = (object) array('data' => array(
			'username' => 'Bob', 'password' => 's3cure', 'group' => 'editors'
		));

		$result = $subject->check($request);
		$this->assertEqual(array_keys($request->data), array_keys($result));
	}

	public function testUncallableGenericValidator() 
	{
		$subject = new Form(array(
			'model'      => __CLASS__,
			'validators' => array(true, 'password' => false)
		));

		$request = (object) array('data' => array('username' => 'Bob'));

		$this->expectException('Authentication validator is not callable.');
		$subject->check($request);
	}

	public function testHashedPasswordAuth() 
	{
		$subject = new Form(array(
			'model'      => __CLASS__,
			'filters'    => array('password' => 'sha1'),
			'validators' => array('password' => false)
		));

		$request = (object) array('data' => array('username' => 'Bob', 'password' => 's3kr1t'));
		$expected = array(
			'username' => 'Bob',
			'password' => 'ff44e879c7e013b38e4b970e8a5d47c7f283eed1'
		);
		$this->assertEqual($expected, $subject->check($request));
	}

	public function testValidatorWithFieldMapping() 
	{
		$subject = new Form(array(
			'model'      => __CLASS__,
			'fields'     => array('name' => 'user.name', 'password' => 'user.password'),
			'validators' => array(
				'password' => function ($form, $data) 
				{
					if($form == $data)
						return true;
					return false;
				}
			)
		));

		$request = (object) array('data' => array('name' => 'Foo', 'password' => 'bar'));
		$this->assertTrue($subject->check($request));
	}
}