<?php

namespace lithium\console\command;

use lithium\console\command\g11n\Extract;

class G11n extends \lithium\console\Command 
{
	public function run() {}
	
	public function extract() 
	{
		$extract = new Extract(array('request' => $this->request));
		return $extract->run();
	}
}