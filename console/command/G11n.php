<?php

namespace arthur\console\command;

use arthur\console\command\g11n\Extract;

class G11n extends \arthur\console\Command 
{
	public function run() {}
	
	public function extract() 
	{
		$extract = new Extract(array('request' => $this->request));
		return $extract->run();
	}
}