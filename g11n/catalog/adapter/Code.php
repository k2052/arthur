<?php

namespace arthur\g11n\catalog\adapter;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use arthur\core\ConfigException;
use arthur\template\view\Compiler;

class Code extends \arthur\g11n\catalog\Adapter 
{
	public function __construct(array $config = array()) 
	{
		$defaults = array('path' => null, 'scope' => null);
		parent::__construct($config + $defaults);
	}
	
	protected function _init() 
	{
		parent::_init();
		if(!is_dir($this->_config['path'])) {
			$message = "Code directory does not exist at path `{$this->_config['path']}`.";
			throw new ConfigException($message);
		}
	}

	public function read($category, $locale, $scope) 
	{
		if($scope != $this->_config['scope'])
			return null;                 
			
		$path = $this->_config['path'];

		switch($category) 
		{
			case 'messageTemplate':
				return $this->_readMessageTemplate($path);
			default:
				return null;
		}
	}

	protected function _readMessageTemplate($path) 
	{
		$base     = new RecursiveDirectoryIterator($path);
		$iterator = new RecursiveIteratorIterator($base);
		$data     = array();

		foreach($iterator as $item) 
		{
			$file = $item->getPathname();

			switch(pathinfo($file, PATHINFO_EXTENSION)) 
			{
				case 'php':
					$data += $this->_parsePhp($file);
				break;
			}
		}     
		
		return $data;
	}

	protected function _parsePhp($file) 
	{
		$contents = file_get_contents($file);
		$contents = Compiler::compile($contents);

		$defaults = array(
			'ids'        => array(),
			'open'       => false,
			'position'   => 0,
			'occurrence' => array('file' => $file, 'line' => null)
		);
		extract($defaults);
		$data = array();

		if(strpos($contents, '$t(') === false && strpos($contents, '$tn(') == false)
			return $data;

		$tokens = token_get_all($contents);
		unset($contents);

		foreach($tokens as $key => $token) 
		{
			if(!is_array($token))
				$token = array(0 => null, 1 => $token, 2 => null);

			if($open) 
			{
				if($position >= ($open === 'singular' ? 1 : 2)) 
				{
					$data = $this->_merge($data, array(
						'id'          => $ids['singular'],
						'ids'         => $ids,
						'occurrences' => array($occurrence)
					));
					extract($defaults, EXTR_OVERWRITE);
				} 
				elseif($token[0] === T_CONSTANT_ENCAPSED_STRING) {
					$ids[$ids ? 'plural' : 'singular'] = $token[1];
					$position++;
				}
			} 
			else
			{
				if(isset($tokens[$key + 1]) && $tokens[$key + 1] === '(') 
				{
					if($token[1] === '$t')
						$open = 'singular';
					elseif($token[1] === '$tn')
						$open = 'plural';
					else
						continue;

					$occurrence['line'] = $token[2];
				}
			}
		}    
		
		return $data;
	}

  protected function _merge(array $data, array $item) 
  {
    $filter = function ($value) use (&$filter) 
    {
      if(is_array($value))
        return array_map($filter, $value);
      return substr($value, 1, -1);        
    };
    $fields = array('id', 'ids', 'translated');

    foreach($fields as $field) {
      if(isset($item[$field]))
        $item[$field] = $filter($item[$field]);     
    }
    return parent::_merge($data, $item);    
  } 
}