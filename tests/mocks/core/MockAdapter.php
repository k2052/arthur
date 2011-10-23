<?php

namespace arthur\tests\mocks\core;

class MockAdapter extends \arthur\core\Adaptable 
{
	protected static $_configurations = array();
	protected static $_adapters = 'adapter.storage.cache';
}