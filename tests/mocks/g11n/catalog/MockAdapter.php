<?php

namespace arthur\tests\mocks\g11n\catalog;

class MockAdapter extends \arthur\g11n\catalog\Adapter 
{

	public function merge($data, $item) 
	{
		return $this->_merge($data, $item);
	}
}