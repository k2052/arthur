<?php

namespace arthur\util;

class Inflector 
{
	protected static $_transliteration = array(
		'/à|á|å|â/' => 'a',
		'/è|é|ê|ẽ|ë/' => 'e',
		'/ì|í|î/' => 'i',
		'/ò|ó|ô|ø/' => 'o',
		'/ù|ú|ů|û/' => 'u',
		'/ç/' => 'c', '/ñ/' => 'n',
		'/ä|æ/' => 'ae', '/ö/' => 'oe',
		'/ü/' => 'ue', '/Ä/' => 'Ae',
		'/Ü/' => 'Ue', '/Ö/' => 'Oe',
		'/ß/' => 'ss'
	);

	protected static $_uninflected = array(
		'Amoyese', 'bison', 'Borghese', 'bream', 'breeches', 'britches', 'buffalo', 'cantus',
		'carp', 'chassis', 'clippers', 'cod', 'coitus', 'Congoese', 'contretemps', 'corps',
		'debris', 'diabetes', 'djinn', 'eland', 'elk', 'equipment', 'Faroese', 'flounder',
		'Foochowese', 'gallows', 'Genevese', 'Genoese', 'Gilbertese', 'graffiti',
		'headquarters', 'herpes', 'hijinks', 'Hottentotese', 'information', 'innings',
		'jackanapes', 'Kiplingese', 'Kongoese', 'Lucchese', 'mackerel', 'Maltese', 'media',
		'mews', 'moose', 'mumps', 'Nankingese', 'news', 'nexus', 'Niasese', 'People',
		'Pekingese', 'Piedmontese', 'pincers', 'Pistoiese', 'pliers', 'Portuguese',
		'proceedings', 'rabies', 'rice', 'rhinoceros', 'salmon', 'Sarawakese', 'scissors',
		'sea[- ]bass', 'series', 'Shavese', 'shears', 'siemens', 'species', 'swine', 'testes',
		'trousers', 'trout','tuna', 'Vermontese', 'Wenchowese', 'whiting', 'wildebeest',
		'Yengeese'
	);

	protected static $_singular = array(
		'rules' => array(
			'/(s)tatuses$/i' => '\1\2tatus',
			'/^(.*)(menu)s$/i' => '\1\2',
			'/(quiz)zes$/i' => '\\1',
			'/(matr)ices$/i' => '\1ix',
			'/(vert|ind)ices$/i' => '\1ex',
			'/^(ox)en/i' => '\1',
			'/(alias)(es)*$/i' => '\1',
			'/(alumn|bacill|cact|foc|fung|nucle|radi|stimul|syllab|termin|viri?)i$/i' => '\1us',
			'/(cris|ax|test)es$/i' => '\1is',
			'/(shoe)s$/i' => '\1',
			'/(o)es$/i' => '\1',
			'/ouses$/' => 'ouse',
			'/uses$/' => 'us',
			'/([m|l])ice$/i' => '\1ouse',
			'/(x|ch|ss|sh)es$/i' => '\1',
			'/(m)ovies$/i' => '\1\2ovie',
			'/(s)eries$/i' => '\1\2eries',
			'/([^aeiouy]|qu)ies$/i' => '\1y',
			'/([lr])ves$/i' => '\1f',
			'/(tive)s$/i' => '\1',
			'/(hive)s$/i' => '\1',
			'/(drive)s$/i' => '\1',
			'/([^fo])ves$/i' => '\1fe',
			'/(^analy)ses$/i' => '\1sis',
			'/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '\1\2sis',
			'/([ti])a$/i' => '\1um',
			'/(p)eople$/i' => '\1\2erson',
			'/(m)en$/i' => '\1an',
			'/(c)hildren$/i' => '\1\2hild',
			'/(n)ews$/i' => '\1\2ews',
			'/^(.*us)$/' => '\\1',
			'/s$/i' => ''
		),
		'irregular' => array(),
		'uninflected' => array(
			'.*[nrlm]ese', '.*deer', '.*fish', '.*measles', '.*ois', '.*pox', '.*sheep', '.*ss'
		)
	);

	protected static $_singularized = array();

	protected static $_plural = array(
		'rules' => array(
			'/(s)tatus$/i' => '\1\2tatuses',
			'/(quiz)$/i' => '\1zes',
			'/^(ox)$/i' => '\1\2en',
			'/([m|l])ouse$/i' => '\1ice',
			'/(matr|vert|ind)(ix|ex)$/i'  => '\1ices',
			'/(x|ch|ss|sh)$/i' => '\1es',
			'/([^aeiouy]|qu)y$/i' => '\1ies',
			'/(hive)$/i' => '\1s',
			'/(?:([^f])fe|([lr])f)$/i' => '\1\2ves',
			'/sis$/i' => 'ses',
			'/([ti])um$/i' => '\1a',
			'/(p)erson$/i' => '\1eople',
			'/(m)an$/i' => '\1en',
			'/(c)hild$/i' => '\1hildren',
			'/(buffal|tomat)o$/i' => '\1\2oes',
			'/(alumn|bacill|cact|foc|fung|nucle|radi|stimul|syllab|termin|vir)us$/i' => '\1i',
			'/us$/' => 'uses',
			'/(alias)$/i' => '\1es',
			'/(ax|cri|test)is$/i' => '\1es',
			'/s$/' => 's',
			'/^$/' => '',
			'/$/' => 's'
		),
		'irregular' => array(
			'atlas' => 'atlases', 'beef' => 'beefs', 'brother' => 'brothers',
			'child' => 'children', 'corpus' => 'corpuses', 'cow' => 'cows',
			'ganglion' => 'ganglions', 'genie' => 'genies', 'genus' => 'genera',
			'graffito' => 'graffiti', 'hoof' => 'hoofs', 'loaf' => 'loaves', 'man' => 'men',
			'money' => 'monies', 'mongoose' => 'mongooses', 'move' => 'moves',
			'mythos' => 'mythoi', 'numen' => 'numina', 'occiput' => 'occiputs',
			'octopus' => 'octopuses', 'opus' => 'opuses', 'ox' => 'oxen', 'penis' => 'penises',
			'person' => 'people', 'sex' => 'sexes', 'soliloquy' => 'soliloquies',
			'testis' => 'testes', 'trilby' => 'trilbys', 'turf' => 'turfs'
		),
		'uninflected' => array(
			'.*[nrlm]ese', '.*deer', '.*fish', '.*measles', '.*ois', '.*pox', '.*sheep'
		)
	);

	protected static $_pluralized = array();
	protected static $_camelized = array();
	protected static $_underscored = array();
	protected static $_humanized = array();

	public static function rules($type, $config = array()) 
	{
		$var = '_' . $type;

		if(!isset(static::${$var})) return null;
		if(empty($config)) return static::${$var};  
			
		switch($type) 
		{
			case 'transliteration':
				$_config = array();

				foreach ($config as $key => $val) {
					if ($key[0] != '/') {
						$key = '/' . join('|', array_filter(preg_split('//u', $key))) . '/';
					}
					$_config[$key] = $val;
				}
				static::$_transliteration = array_merge(
					$_config, static::$_transliteration, $_config
				);
			break;
			case 'uninflected':
				static::$_uninflected = array_merge(static::$_uninflected, (array) $config);
				static::$_plural['regexUninflected'] = null;
				static::$_singular['regexUninflected'] = null;

				foreach ((array) $config as $word) {
					unset(static::$_singularized[$word], static::$_pluralized[$word]);
				}
			break;
			case 'singular':
			case 'plural':
				if(isset(static::${$var}[key($config)])) 
				{
					foreach($config as $rType => $set) 
					{
						static::${$var}[$rType] = array_merge($set, static::${$var}[$rType], $set);

						if($rType == 'irregular') {
							$swap = ($type == 'singular' ? '_plural' : '_singular');
							static::${$swap}[$rType] = array_flip(static::${$var}[$rType]);
						}
					}
				} 
				else 
				{
					static::${$var}['rules'] = array_merge(
						$config, static::${$var}['rules'], $config
					);
				}
			break;
		}
	}

	public static function pluralize($word) 
	{
		if(isset(static::$_pluralized[$word]))
			return static::$_pluralized[$word];

		extract(static::$_plural);

		if(!isset($regexUninflected) || !isset($regexIrregular)) 
		{
			$regexUninflected = static::_enclose(join( '|', $uninflected + static::$_uninflected));
			$regexIrregular = static::_enclose(join( '|', array_keys($irregular)));
			static::$_plural += compact('regexUninflected', 'regexIrregular');
		}
		if(preg_match('/^(' . $regexUninflected . ')$/i', $word, $regs))
			return static::$_pluralized[$word] = $word;

		if(preg_match('/(.*)\\b(' . $regexIrregular . ')$/i', $word, $regs)) {
			$plural = substr($word, 0, 1) . substr($irregular[strtolower($regs[2])], 1);
			return static::$_pluralized[$word] = $regs[1] . $plural;
		}
		foreach($rules as $rule => $replacement) {
			if(preg_match($rule, $word))
				return static::$_pluralized[$word] = preg_replace($rule, $replacement, $word);
		}   
		
		return static::$_pluralized[$word] = $word;
	}

	public static function singularize($word) 
	{
		if(isset(static::$_singularized[$word])) 
			return static::$_singularized[$word];
		if(empty(static::$_singular['irregular']))
			static::$_singular['irregular'] = array_flip(static::$_plural['irregular']);
		
		extract(static::$_singular);

		if(!isset($regexUninflected) || !isset($regexIrregular)) 
		{
			$regexUninflected = static::_enclose(join('|', $uninflected + static::$_uninflected));
			$regexIrregular = static::_enclose(join('|', array_keys($irregular)));
			static::$_singular += compact('regexUninflected', 'regexIrregular');
		}
		if(preg_match("/(.*)\\b({$regexIrregular})\$/i", $word, $regs)) {
			$singular = substr($word, 0, 1) . substr($irregular[strtolower($regs[2])], 1);
			return static::$_singularized[$word] = $regs[1] . $singular;
		}
		if(preg_match('/^(' . $regexUninflected . ')$/i', $word, $regs))
			return static::$_singularized[$word] = $word;         
			
		foreach($rules as $rule => $replacement) {
			if(preg_match($rule, $word)) 
				return static::$_singularized[$word] = preg_replace($rule, $replacement, $word);
		}                  
		
		return static::$_singularized[$word] = $word;
	}

	public static function reset() 
	{
		static::$_singularized = static::$_pluralized = array();
		static::$_camelized    = static::$_underscored = array();
		static::$_humanized    = array();

		static::$_plural['regexUninflected'] = static::$_singular['regexUninflected'] = null;
		static::$_plural['regexIrregular']   = static::$_singular['regexIrregular']   = null;  
		
		static::$_transliteration = array(
			'/à|á|å|â/' => 'a', '/è|é|ê|ẽ|ë/' => 'e',
			'/ì|í|î/' => 'i', '/ò|ó|ô|ø/' => 'o',
			'/ù|ú|ů|û/' => 'u', '/ç/' => 'c',
			'/ñ/' => 'n', '/ä|æ/' => 'ae', '/ö/' => 'oe',
			'/ü/' => 'ue', '/Ä/' => 'Ae',
			'/Ü/' => 'Ue', '/Ö/' => 'Oe',
			'/ß/' => 'ss'
		);
	}

	public static function camelize($word, $cased = true) 
	{
		$_word = $word;

		if(isset(static::$_camelized[$_word]) && $cased)
			return static::$_camelized[$_word];

		$word = str_replace(" ", "", ucwords(str_replace(array("_", '-'), " ", $word)));

		if(!$cased)
			return lcfirst($word);

		return static::$_camelized[$_word] = $word;
	}

	public static function underscore($word) 
	{
		if(isset(static::$_underscored[$word]))
			return static::$_underscored[$word];  
			
		return static::$_underscored[$word] = strtolower(static::slug($word, '_'));
	}

	public static function slug($string, $replacement = '-') 
	{
		$map = static::$_transliteration + array(
			'/[^\w\s]/' => ' ', '/\\s+/' => $replacement,
			'/(?<=[a-z])([A-Z])/' => $replacement . '\\1',
			str_replace(':rep', preg_quote($replacement, '/'), '/^[:rep]+|[:rep]+$/') => ''
		);              
		
		return preg_replace(array_keys($map), array_values($map), $string);
	}

	public static function humanize($word, $separator = '_') 
	{
		if(isset(static::$_humanized[$key = $word . ':' . $separator])) 
			return static::$_humanized[$key];

		return static::$_humanized[$key] = ucwords(str_replace($separator, " ", $word));
	}

	public static function tableize($className) 
	{
		return static::pluralize(static::underscore($className));
	}

	public static function classify($tableName) 
	{
		return static::camelize(static::singularize($tableName));
	}

	protected static function _enclose($string) 
	{
		return '(?:' . $string . ')';
	}
}