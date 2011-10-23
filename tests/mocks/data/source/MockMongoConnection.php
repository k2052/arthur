<?php

namespace arthur\tests\mocks\data\source;

class MockMongoConnection extends \arthur\data\source\MongoDb 
{
	public function connect() 
	{
		return false;
	}
}