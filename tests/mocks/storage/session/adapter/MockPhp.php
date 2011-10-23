<?php

namespace arthur\tests\mocks\storage\session\adapter;

class MockPhp extends \arthur\storage\session\adapter\Php 
{
	public static function isStarted() 
	{
		return false;
	}

	protected static function _startup() 
	{
		return false;
	}
}