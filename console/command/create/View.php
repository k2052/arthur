<?php

namespace arthur\console\command\create;

use arthur\util\Inflector;
use arthur\util\String;

class View extends \arthur\console\command\Create 
{
	protected function _save(array $params = array()) 
	{
		$params['path'] = Inflector::underscore($this->request->action);
		$params['file'] = $this->request->args(0);

		$contents = $this->_template();
		$result   = String::insert($contents, $params);

		if(!empty($this->_library['path'])) 
		{
			$path      = $this->_library['path'] . "/views/{$params['path']}/{$params['file']}";
			$file      = str_replace('//', '/', "{$path}.php");
			$directory = dirname($file);

			if(!is_dir($directory))
			{
				if(!mkdir($directory, 0755, true))
					return false;
			}
			$directory = str_replace($this->_library['path'] . '/', '', $directory);

			if(file_put_contents($file, "<?php\n\n{$result}\n\n?>"))
				return "{$params['file']}.php created in {$directory}.";
		}  
		
		return false;
	}
}