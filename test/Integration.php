<?php

namespace arthur\test;

class Integration extends \arthur\test\Unit 
{
	protected function _init() 
	{
		parent::_init();

		$this->applyFilter('run', function($self, $params, $chain) 
		{
			$before = $self->results();

			$chain->next($self, $params, $chain);

			$after = $self->results();

			while(count($after) > count($before)) 
			{
				$result = array_pop($after);
				
				if($result['result'] == 'fail') return false;
			}
		});
	}
}