<?php

namespace arthur\tests\mocks\core;

class MockStrategy extends \arthur\core\Adaptable 
{
	protected static $_configurations = array();
	protected static $_strategies = 'strategy.storage.cache';
}