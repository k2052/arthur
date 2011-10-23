<?php

namespace arthur\tests\mocks\data\source\database\adapter;

class MockSqlite3 extends \arthur\data\source\database\adapter\Sqlite3 
{
	public function get($var)
	{
		return $this->{$var};
	}
}