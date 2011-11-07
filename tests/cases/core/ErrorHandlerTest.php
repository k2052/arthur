<?php

namespace lithium\tests\cases\core;

use Closure;
use Exception;
use UnexpectedValueException;
use lithium\core\ErrorHandler;

class ErrorHandlerTest extends \lithium\test\Unit 
{
	public $errors = array();

	public function setUp() 
	{
		if(!ErrorHandler::isRunning())
			ErrorHandler::run();

		ErrorHandler::reset();
		$this->errors = array();
	}

	public function tearDown() 
	{
		if(ErrorHandler::isRunning())
			ErrorHandler::stop();
	}

	public function testExceptionCatching() 
	{
		$self = $this;
		ErrorHandler::config(array(array(
			'type' => 'Exception',
			'handler' => function($info) use ($self) 
			{
				$self->errors[] = $info;
			}
		)));

		ErrorHandler::handle(new Exception('Test!'));

		$this->assertEqual(1, count($this->errors));
		$result   = end($this->errors);
		$expected = 'Test!';
		$this->assertEqual($expected, $result['message']);

		$this->expectException('/Test/');
		trigger_error('Test warning!', E_USER_WARNING);
		$this->assertEqual(1, count($this->errors));
	}

	public function testExceptionSubclassCatching() 
	{
		$self = $this;
		ErrorHandler::config(array(array(
			'type' => 'Exception',
			'handler' => function($info) use ($self)
			{
				$self->errors[] = $info;
			}
		)));
		ErrorHandler::handle(new UnexpectedValueException('Test subclass'));

		$this->assertEqual(1, count($this->errors));
		$result   = end($this->errors);
		$expected = 'Test subclass';
		$this->assertEqual($expected, $result['message']);
	}

	public function testErrorCatching() 
	{
		$this->skipIf(true, 'Refactoring original error-handling iteration.');

		$self = $this;
		ErrorHandler::config(array(array(
			'code' => E_WARNING | E_USER_WARNING,
			'handler' => function($info) use ($self) {
				$self->errors[] = $info;
			}
		)));

		file_get_contents(false);
		$this->assertEqual(1, count($this->errors));

		$result = end($this->errors);
		$this->assertPattern('/Filename cannot be empty/', $result['message']);

		trigger_error('Test warning', E_USER_WARNING);
		$this->assertEqual(2, count($this->errors));

		$result = end($this->errors);
		$this->assertEqual('Test warning', $result['message']);

		trigger_error('Test notice', E_USER_NOTICE);
		$this->assertEqual(2, count($this->errors));
	}

	public function testReset() 
	{
		ErrorHandler::reset();
		$this->assertEqual(array(), ErrorHandler::handlers());

		$result = ErrorHandler::handlers(array('test' => function($error) { /* Do something */ }));
		$this->assertEqual(array('test'), array_keys($result));
		$this->assertTrue($result['test'] instanceof Closure);
		$this->assertEqual($result, ErrorHandler::handlers());

		ErrorHandler::reset();
		$this->assertEqual(array(), ErrorHandler::handlers());
	}

	public function testApply() 
	{
		$subject = new ErrorHandlerTest();
		ErrorHandler::apply(array($subject, 'throwException'), array(), function($details) {
			return $details['exception']->getMessage();
		});
		$this->assertEqual('foo', $subject->throwException());
	}

	public function throwException() 
	{
		return $this->_filter(__METHOD__, array(), function($self, $params) 
		{
			throw new Exception('foo');
			return 'bar';
		});
	}

	public function testTrace() 
	{
		$current = debug_backtrace();
		$results = ErrorHandler::trace($current);
		$this->assertEqual(count($current), count($results));
		$this->assertEqual($results[0], 'lithium\tests\cases\core\ErrorHandlerTest::testTrace');
	}

	public function testRun() 
	{
		ErrorHandler::stop();
		$this->assertEqual(ErrorHandler::isRunning(), false);
		ErrorHandler::run();
		$this->assertEqual(ErrorHandler::isRunning(), true);
		ErrorHandler::stop();
		$this->assertEqual(ErrorHandler::isRunning(), false);
	}

	public function testErrorTrapping() 
	{
		ErrorHandler::stop();
		ErrorHandler::run(array('trapErrors' => true));

		// Undefined offset error shouldn't surface.
		list($foo, $bar) = array('baz');
	}
}