<?php

namespace arthur\net\http;

class Route extends \arthur\core\Object 
{
	protected $_template = '';
	protected $_pattern = '';
	protected $_keys = array();
	protected $_params = array();
	protected $_match = array();
	protected $_meta = array();
	protected $_defaults = array();
	protected $_subPatterns = array();
	protected $_persist = array();
	protected $_handler = null;
	
	protected $_autoConfig = array(
		'template', 'pattern', 'params', 'match', 'meta',
		'keys', 'defaults', 'subPatterns', 'persist', 'handler'
	);

	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'params'   => array(),
			'template' => '/',
			'pattern'  => '',
			'match'    => array(),
			'meta'     => array(),
			'defaults' => array(),
			'keys'     => array(),
			'persist'  => array(),
			'handler'  => null,
			'continue' => false
		);    
		
		parent::__construct($config + $defaults);
	}

	protected function _init() 
	{
		parent::_init();

		if(!$this->_config['continue']) 
			$this->_params += array('action' => 'index');
		if(!$this->_config['pattern']) 
			$this->compile();
		if($isKey = isset($this->_keys['controller']) || isset($this->_params['controller']))
			$this->_persist = $this->_persist ?: array('controller');
	}

	public function parse($request, array $options = array()) 
	{
		$defaults = array('url' => $request->url);
		$options += $defaults;
		$url = '/' . trim($options['url'], '/');

		if(!preg_match($this->_pattern, $url, $match))
			return false;
		foreach($this->_meta as $key => $compare) 
		{
			$value = $request->get($key);

			if(!($compare == $value || (is_array($compare) && in_array($value, $compare))))
				return false;
		}

		if(isset($match['args']))
			$match['args'] = explode('/', $match['args']);
		if(isset($this->_keys['args']))
			$match += array('args' => array());
		$result = array_intersect_key($match, $this->_keys) + $this->_params + $this->_defaults;

		if(isset($result['action']) && !$result['action']) 
			$result['action'] = 'index';      
			
		$request->params  = $result + (array) $request->params;
		$request->persist = array_unique(array_merge($request->persist, $this->_persist));

		if($this->_handler) {
			$handler = $this->_handler;
			return $handler($request);
		}    
		
		return $request;
	}

	public function match(array $options = array(), $context = null) 
	{
		$defaults = array('action' => 'index');
		$query = null;

		if(!$this->_config['continue']) 
		{
			$options += $defaults;

			if(isset($options['?'])) 
			{
				$query = $options['?'];
				$query = '?' . (is_array($query) ? http_build_query($query) : $query);
				unset($options['?']);
			}
		}

		if(!$options = $this->_matchKeys($options))
			return false; 
			
		foreach($this->_subPatterns as $key => $pattern) {
			if(isset($options[$key]) && !preg_match("/^{$pattern}$/", $options[$key]))
				return false;
		}
		$defaults = $this->_defaults + $defaults;

		if($this->_config['continue'])
			return $this->_write(array('args' => '{:args}') + $options, $this->_defaults);

		return $this->_write($options, $defaults + array('args' => '')) . $query;
	}

	public function canContinue() 
	{
		return $this->_config['continue'];
	}

	protected function _matchKeys($options) 
	{
		$args = array('args' => 'args');

		if(array_intersect_key($options, $this->_match) != $this->_match)
			return false;

		if(!$this->_config['continue']) {
			if(array_diff_key(array_diff_key($options, $this->_match), $this->_keys) !== array())
				return false;
		}
		$options += $this->_defaults;

		if(array_intersect_key($this->_keys, $options) + $args !== $this->_keys + $args)
			return false;

		return $options;
	}

	protected function _write($options, $defaults) 
	{
		$template = $this->_template;
		$trimmed = true;

		if(isset($options['args']) && is_array($options['args']))
			$options['args'] = join('/', $options['args']);

		$options += array('args' => '');

		foreach(array_reverse($this->_keys, true) as $key) 
		{
			$value   =& $options[$key];
			$pattern = isset($this->_subPatterns[$key]) ? ":{$this->_subPatterns[$key]}" : '';
			$rpl     = "{:{$key}{$pattern}}";
			$len     = strlen($rpl) * -1;

			if($trimmed && isset($defaults[$key]) && $value == $defaults[$key]) 
			{
				if(substr($template, $len) == $rpl) {
					$template = rtrim(substr($template, 0, $len), '/');
					continue;
				}
			}
			if($value === null) {
				$template = str_replace("/{$rpl}", '', $template);
				continue;
			}
			if($key !== 'args') $trimmed = false;

			$template = str_replace($rpl, $value, $template);
		}      
		
		return $template;
	}

	public function export() 
	{
		$result = array();

		foreach($this->_autoConfig as $key) {
			$result[$key] = $this->{'_' . $key};
		}    
		
		return $result;
	}

	public function compile() 
	{
		$this->_match = $this->_params;

		foreach($this->_params as $key => $value) 
		{
			if(!strpos($key, ':'))
				continue;

			unset($this->_params[$key]);
			$this->_meta[$key] = $value;
		}

		if($this->_template === '/' || $this->_template === '') {
			$this->_pattern = '@^/*$@';
			return;
		} 
		
		$this->_pattern = "@^{$this->_template}\$@";
	  $match          = '@([/.])?\{:([^:}]+):?((?:[^{]+(?:\{[0-9,]+\})?)*?)\}@S';
		preg_match_all($match, $this->_pattern, $m);

		if(!$tokens = $m[0]) return;
			
		$slashes = $m[1];
		$params  = $m[2];
		$regexs  = $m[3];
		unset($m);
		$this->_keys = array();

		foreach($params as $i => $param) {
			$this->_keys[$param] = $param;
			$this->_pattern = $this->_regex($regexs[$i], $param, $tokens[$i], $slashes[$i]);
		}
		$this->_defaults = array_intersect_key($this->_params, $this->_keys);
		$this->_match    = array_diff_key($this->_params, $this->_defaults);
	}

	protected function _regex($regex, $param, $token, $prefix) 
	{
		if($regex)
			$this->_subPatterns[$param] = $regex;
		elseif ($param == 'args')
			$regex = '.*';
		else 
			$regex = '[^\/]+';

		$req = $param === 'args' || array_key_exists($param, $this->_params) ? '?' : '';

		if($prefix === '/')
			$pattern = "(?:/(?P<{$param}>{$regex}){$req}){$req}";
		elseif ($prefix === '.') 
			$pattern = "\\.(?P<{$param}>{$regex}){$req}"; 
		else 
			$pattern = "(?P<{$param}>{$regex}){$req}";

		return str_replace($token, $pattern, $this->_pattern);
	}
}