<?php

namespace arthur\analysis;

class Docblock extends \arthur\core\StaticObject 
{
	public static $tags = array(
		'todo', 'discuss', 'fix', 'important', 'var',
		'param', 'return', 'throws', 'see', 'link',
		'task', 'dependencies', 'filter'
	);

	public static function comment($comment) 
	{
		$text        = null;
		$tags        = array();
		$description = null;
		$comment     = trim(preg_replace('/^(\s*\/\*\*|\s*\*{1,2}\/|\s*\* ?)/m', '', $comment));
		$comment     = str_replace("\r\n", "\n", $comment);

		if($items = preg_split('/\n@/ms', $comment, 2)) {
			list($description, $tags) = $items + array('', '');
			$tags = $tags ? static::tags("@{$tags}") : array();
		}

		if(strpos($description, "\n\n"))
			list($description, $text) = explode("\n\n", $description, 2);
		$text        = trim($text);
		$description = trim($description);     
		
		return compact('description', 'text', 'tags');
	}

	public static function tags($string) 
	{
		$regex  = '/\n@(?P<type>' . join('|', static::$tags) . ")/msi";
		$string = trim($string);

		$result = preg_split($regex, "\n$string", -1, PREG_SPLIT_DELIM_CAPTURE);
		$tags  = array();

		for($i = 1; $i < count($result) - 1; $i += 2) 
		{
			$type = trim(strtolower($result[$i]));
			$text = trim($result[$i + 1]);

			if(isset($tags[$type])) {
				$tags[$type] = is_array($tags[$type]) ? $tags[$type] : (array) $tags[$type];
				$tags[$type][] = $text;
			} 
			else 
				$tags[$type] = $text;		
		}

		if(isset($tags['param'])) 
		{
			$params = $tags['param'];
			$tags['params'] = static::_params((array) $tags['param']);
			unset($tags['param']);
		}   
		
		return $tags;
	}

	protected static function _params(array $params) 
	{
		$result = array(); 
		
		foreach($params as $param) 
		{
			$param = explode(' ', $param, 3);
			$type = $name = $text = null;

			foreach(array('type', 'name', 'text') as $i => $key) 
			{
				if(!isset($param[$i]))
					break;
				${$key} = $param[$i];
			}
			if($name) 
				$result[$name] = compact('type', 'text');
		}    
		
		return $result;
	}
}