<?php

namespace arthur\util;

class Set 
{
	public static function append(array $array, array $array2) 
	{
		if(!$array && $array2)
			return $array2;          
			
		foreach($array2 as $key => $value) 
		{
			if(!isset($array[$key]))
				$array[$key] = $value;
			elseif(is_array($value)) 
				$array[$key] = static::append($array[$key], $array2[$key]);
		}     
		
		return $array;
	}

	public static function check($data, $path = null) 
	{
		if(!$path) return $data;
		$path = is_array($path) ? $path : explode('.', $path);

		foreach($path as $i => $key) 
		{
			if(is_numeric($key) && intval($key) > 0 || $key === '0')
				$key = intval($key);
			if($i === count($path) - 1)
				return (is_array($data) && isset($data[$key]));
			else 
			{
				if(!is_array($data) || !isset($data[$key]))
					return false;
				$data =& $data[$key];
			}
		}
	}

	public static function combine($data, $path1 = null, $path2 = null, $groupPath = null) 
	{
		if(!$data)
			return array();
		if(is_object($data)) 
			$data = get_object_vars($data);    
			
		if(is_array($path1)) {
			$format = array_shift($path1);
			$keys = static::format($data, $format, $path1);
		} 
		else {
			$keys = static::extract($data, $path1);
		}
		$vals = array(); 
		
		if(!empty($path2) && is_array($path2)) {
			$format = array_shift($path2);
			$vals = static::format($data, $format, $path2);
		} 
		elseif(!empty($path2)) {
			$vals = static::extract($data, $path2);
		}
		$valCount = count($vals);
		$count    = count($keys);

		for($i = $valCount; $i < $count; $i++) {
			$vals[$i] = null;
		}  
		
		if($groupPath != null) 
		{
			$group = static::extract($data, $groupPath);
			if(!empty($group)) 
			{
				$c = count($keys);
				for($i = 0; $i < $c; $i++) 
				{
					if(!isset($group[$i]))
						$group[$i] = 0;
					if(!isset($out[$group[$i]]))
						$out[$group[$i]] = array();

					$out[$group[$i]][$keys[$i]] = $vals[$i];
				} 
				
				return $out;
			}
		}   
		
		return array_combine($keys, $vals);
	}

	public static function contains(array $array1, array $array2) 
	{
		if(!$array1 || !$array2)
			return false;
		foreach($array2 as $key => $val) 
		{
			if(!isset($array1[$key]) || $array1[$key] != $val)
				return false;
			if(is_array($val) && !static::contains($array1[$key], $val))
				return false;
		}   
		
		return true;
	}

	public static function depth($data, array $options = array()) 
	{
		$defaults = array('all' => false, 'count' => 0);
		$options += $defaults;

 		if(!$data) 
			return 0;

		if(!$options['all'])
			return (is_array(reset($data))) ? static::depth(reset($data)) + 1 : 1;

		$depth = array($options['count']);

		if(is_array($data) && reset($data) !== false) 
		{
			foreach($data as $value) 
			{
				$depth[] = static::depth($value, array(
					'all' => $options['all'],
					'count' => $options['count'] + 1
				));
			}
		}      
		
		return max($depth);
	}

	public static function diff(array $val1, array $val2) 
	{
		if(!$val1 || !$val2) 
			return $val2 ?: $val1;

		$out = array();

		foreach($val1 as $key => $val) 
		{
			$exists = isset($val2[$key]);

			if(($exists && $val2[$key] != $val) || !$exists)
				$out[$key] = $val;

			unset($val2[$key]);
		}

		foreach($val2 as $key => $val) {
			if(!isset($out[$key])) 
				$out[$key] = $val;
		}      
		
		return $out;
	}

	public static function extract(array $data, $path = null, array $options = array()) 
	{
		if(!$data)
			return array();

		if(is_string($data)) 
		{
			$tmp = $path;
			$path = $data;
			$data = $tmp;
			unset($tmp);
		}

		if($path === '/') 
		{
			return array_filter($data, function($data) {
				return ($data === 0 || $data === '0' || !empty($data));
			});
		}
		$contexts = $data;
		$defaults = array('flatten' => true);
		$options += $defaults;

		if(!isset($contexts[0]))
			$contexts = array($data);

		$tokens = array_slice(preg_split('/(?<!=)\/(?![a-z-]*\])/', $path), 1);

		do 
		{
			$token = array_shift($tokens);
			$conditions = false;

			if(preg_match_all('/\[([^=]+=\/[^\/]+\/|[^\]]+)\]/', $token, $m)) {
				$conditions = $m[1];
				$token = substr($token, 0, strpos($token, '['));
			}
			$matches = array();

			foreach($contexts as $key => $context) 
			{
				if(!isset($context['trace']))
					$context = array('trace' => array(null), 'item' => $context, 'key' => $key);
				if($token === '..') 
				{
					if(count($context['trace']) == 1)
						$context['trace'][] = $context['key'];

					$parent = join('/', $context['trace']) . '/.';
					$context['item'] = static::extract($data, $parent);
					$context['key'] = array_pop($context['trace']);      
					
					if(isset($context['trace'][1]) && $context['trace'][1] > 0)
						$context['item'] = $context['item'][0];
					elseif (!empty($context['item'][$key]))
						$context['item'] = $context['item'][$key];
					else
						$context['item'] = array_shift($context['item']);

					$matches[] = $context;
					continue;
				}
				$match = false;  
				
				if($token === '@*' && is_array($context['item'])) 
				{
					$matches[] = array(
						'trace' => array_merge($context['trace'], (array) $key),
						'key' => $key,
						'item' => array_keys($context['item'])
					);
				}
				elseif(is_array($context['item']) && isset($context['item'][$token])) 
				{
					$items = $context['item'][$token];
					if(!is_array($items))
						$items = array($items);
					elseif(!isset($items[0])) 
					{
						$current = current($items);
						if((is_array($current) && count($items) <= 1) || !is_array($current))
							$items = array($items);
					}

					foreach($items as $key => $item) 
					{
						$ctext = array($context['key']);   
						
						if(!is_numeric($key)) 
						{
							$ctext[] = $token;
							$token = array_shift($tokens);
							
							if(isset($items[$token])) 
							{
								$ctext[] = $token;
								$item = $items[$token];
								$matches[] = array(
									'trace' => array_merge($context['trace'], $ctext),
									'key' => $key,
									'item' => $item
								);
								break;
							} 
							else 
								array_unshift($tokens, $token);
						} 
						else
							$key = $token;

						$matches[] = array(
							'trace' => array_merge($context['trace'], $ctext),
							'key' => $key,
							'item' => $item
						);
					}
				} 
				elseif (
					($key === $token || (ctype_digit($token) && $key == $token) || $token === '.')
				) 
				{
					$context['trace'][] = $key;
					$matches[] = array(
						'trace' => $context['trace'],
						'key' => $key,
						'item' => $context['item']
					);
				}
			}
			if($conditions) 
			{
				foreach($conditions as $condition) 
				{
					$filtered = array();
					$length = count($matches);

					foreach($matches as $i => $match) {
						if(static::matches($match['item'], array($condition), $i + 1, $length))
							$filtered[] = $match;
					}
					$matches = $filtered;
				}
			}
			$contexts = $matches;

			if(empty($tokens))
				break;

		} while (1);

		$r = array();

		foreach($matches as $match) 
		{
			if((!$options['flatten'] || is_array($match['item'])) && !is_int($match['key']))
				$r[] = array($match['key'] => $match['item']);
			else
				$r[] = $match['item'];
		}   
		
		return $r;
	}

	public static function flatten($data, array $options = array()) 
	{
		$defaults = array('separator' => '.', 'path' => null);
		$options += $defaults;
		$result   = array();

		if(!is_null($options['path']))
			$options['path'] .= $options['separator'];
		foreach($data as $key => $val) 
		{
			if(!is_array($val)) {
				$result[$options['path'] . $key] = $val;
				continue;
			}
			$opts    = array('separator' => $options['separator'], 'path' => $options['path'] . $key);
			$result += (array) static::flatten($val, $opts);
		}    
		
		return $result;
	}
	
	public static function expand(array $data, array $options = array()) 
	{
		$defaults = array('separator' => '.');
		$options += $defaults;
		$result   = array();

		foreach($data as $key => $val) 
		{
			if(strpos($key, $options['separator']) === false) {
				$result[$key] = $val;
				continue;
			}
			list($path, $key) = explode($options['separator'], $key, 2);
			$path = is_numeric($path) ? intval($path) : $path;
			$result[$path][$key] = $val;
		}
		foreach($result as $key => $value) 
		{
			if(is_array($value)) 
				$result[$key] = static::expand($value, $options);
		}   
		
		return $result;
	}

	public static function format($data, $format, $keys) 
	{
		$extracted = array();
		$count = count($keys);

		if(!$count) return;

		for($i = 0; $i < $count; $i++) {
			$extracted[] = static::extract($data, $keys[$i]);
		}
		$out   = array();
		$data  = $extracted;
		$count = count($data[0]);

		if(preg_match_all('/\{([0-9]+)\}/msi', $format, $keys2) && isset($keys2[1])) 
		{
			$keys = $keys2[1];
			$format = preg_split('/\{([0-9]+)\}/msi', $format);
			$count2 = count($format);

			for($j = 0; $j < $count; $j++) 
			{
				$formatted = '';
				for($i = 0; $i <= $count2; $i++) 
				{
					if(isset($format[$i])) 
						$formatted .= $format[$i];
					if(isset($keys[$i]) && isset($data[$keys[$i]][$j]))
						$formatted .= $data[$keys[$i]][$j];
				}
				$out[] = $formatted;
			}    
			
			return $out;
		}
		$count2 = count($data);

		for($j = 0; $j < $count; $j++) 
		{
			$args = array();

			for($i = 0; $i < $count2; $i++) {
				if(isset($data[$i][$j])) 
					$args[] = $data[$i][$j];
			}
			$out[] = vsprintf($format, $args);
		}    
		
		return $out;
	}

	public static function insert($list, $path, $data = array()) 
	{
		if (!is_array($path)) {
			$path = explode('.', $path);
		}
		$_list =& $list;

		foreach($path as $i => $key) 
		{
			if(is_numeric($key) && intval($key) > 0 || $key === '0') 
				$key = intval($key);
			if($i === count($path) - 1) 
				$_list[$key] = $data;
			else 
			{
				if(!isset($_list[$key]))
					$_list[$key] = array();    
					
				$_list =& $_list[$key];
			}
		}        
		
		return $list;
	}

	public static function isNumeric($array = null) 
	{
		if(empty($array))
			return null;
		if($array === range(0, count($array) - 1))
			return true;

		$numeric = true;
		$keys    = array_keys($array);
		$count   = count($keys);

		for($i = 0; $i < $count; $i++) 
		{
			if(!is_numeric($array[$keys[$i]])) {
				$numeric = false;
				break;
			}
		}   
		
		return $numeric;
	}

	public static function matches($data = array(), $conditions, $i = null, $length = null) 
	{
		if(!$conditions)
			return true;
		if(is_string($conditions))
			return (boolean) static::extract($data, $conditions); 
			
		foreach($conditions as $condition) 
		{
			if($condition === ':last') 
			{
				if($i != $length) return false;  
				
				continue;
			} 
			elseif($condition === ':first') 
			{
				if($i != 1) return false;
				
				continue;
			}
			if(!preg_match('/(.+?)([><!]?[=]|[><])(.*)/', $condition, $match)) 
			{
				if(ctype_digit($condition)) 
				{
					if($i != $condition)
						return false;
				} 
				elseif(preg_match_all('/(?:^[0-9]+|(?<=,)[0-9]+)/', $condition, $matches))
					return in_array($i, $matches[0]);
				elseif(!isset($data[$condition])) 
					return false;
				
				continue;
			}
			list(,$key,$op,$expected) = $match;

			if(!isset($data[$key])) return false;   
			
			$val = $data[$key];

			if($op === '=' && $expected && $expected{0} === '/')
				return preg_match($expected, $val);
			elseif($op === '=' &&  $val != $expected) 
				return false;
			elseif($op === '!=' && $val == $expected)
				return false;
			elseif($op === '>' && $val <= $expected)
				return false;                          
			elseif($op === '<' && $val >= $expected)
				return false;                          
			elseif($op === '<=' && $val > $expected)
				return false;                          
			elseif($op === '>=' && $val < $expected)
				return false;
		} 
		
		return true;
	}

	public static function merge(array $array1, array $array2) 
	{
		$args = array($array1, $array2);

		if(!$array1 || !$array2)
			return $array1 ?: $array2;
		$result = (array) current($args);

		while(($arg = next($args)) !== false) 
		{
			foreach((array) $arg as $key => $val) 
			{
				if(is_array($val) && isset($result[$key]) && is_array($result[$key]))
					$result[$key] = static::merge($result[$key], $val);
				elseif(is_int($key))
					$result[] = $val;
				else
					$result[$key] = $val;
			}
		}   
		
		return $result;
	}

	public static function normalize($list, $assoc = true, $sep = ',', $trim = true) 
	{
		if(is_string($list)) 
		{
			$list = explode($sep, $list);
			$list = ($trim) ? array_map('trim', $list) : $list;
			return ($assoc) ? static::normalize($list) : $list;
		}

		if(!is_array($list))
			return $list;

		$keys    = array_keys($list);
		$count   = count($keys);
		$numeric = true;

		if(!$assoc) 
		{
			for($i = 0; $i < $count; $i++) 
			{
				if(!is_int($keys[$i])) {
					$numeric = false;
					break;
				}
			}
		}

		if(!$numeric || $assoc) 
		{
			$newList = array();
			for($i = 0; $i < $count; $i++) 
			{
				if(is_int($keys[$i]) && is_scalar($list[$keys[$i]]))
					$newList[$list[$keys[$i]]] = null;
				else 
					$newList[$keys[$i]] = $list[$keys[$i]];
			}
			$list = $newList;
		}     
		
		return $list;
	}

	public static function remove($list, $path = null) 
	{
		if(empty($path))
			return $list;
		if(!is_array($path))
			$path = explode('.', $path);
		$_list =& $list;

		foreach($path as $i => $key) 
		{
			if (is_numeric($key) && intval($key) > 0 || $key === '0') {
				$key = intval($key);
			}
			if ($i === count($path) - 1) {
				unset($_list[$key]);
			} else {
				if (!isset($_list[$key])) {
					return $list;
				}
				$_list =& $_list[$key];
			}
		}
		return $list;
	}

	public static function sort($data, $path, $dir = 'asc') 
	{
		$flatten = function($flatten, $results, $key = null) 
		{
			$stack = array();
			foreach((array) $results as $k => $r) 
			{
				$id = $k;
				if(!is_null($key)) 
					$id = $key;
				if(is_array($r)) 
					$stack = array_merge($stack, $flatten($flatten, $r, $id));
				else 
					$stack[] = array('id' => $id, 'value' => $r);
			} 
			
			return $stack;
		};
		
		$extract = static::extract($data, $path);
		$result  = $flatten($flatten, $extract);

		$keys   = static::extract($result, '/id');
		$values = static::extract($result, '/value');

		$dir = ($dir === 'desc') ? SORT_DESC : SORT_ASC;
		array_multisort($values, $dir, $keys, $dir);
		$sorted = array();
		$keys   = array_unique($keys);

		foreach($keys as $k) {
			$sorted[] = $data[$k];
		}  
		
		return $sorted;
	}
}