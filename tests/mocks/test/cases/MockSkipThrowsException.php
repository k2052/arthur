<?php

namespace arthur\tests\mocks\test\cases;

use Exception;

class MockSkipThrowsException extends \arthur\test\Unit 
{
	public function skip() 
	{
		throw new Exception('skip throws exception');
	}
}