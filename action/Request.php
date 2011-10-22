<?php                

namespace arthur\action;

use arthur\util\Set;
use arthur\util\Validator;

class Request extends \arthur\net\http\Request 
{
	public $url = null;
	public $params = array();
	public $persist = array();
	public $data = array();
	public $query = array();
	protected $_base = null;
	protected $_env = array();
	protected $_classes = array('media' => 'arthur\net\http\Media');
	protected $_stream = null;
	protected $_detectors = array(
		'mobile'  => array('HTTP_USER_AGENT', null),
		'ajax'    => array('HTTP_X_REQUESTED_WITH', 'XMLHttpRequest'),
		'flash'   => array('HTTP_USER_AGENT', 'Shockwave Flash'),
		'ssl'     => 'HTTPS',
		'get'     => array('REQUEST_METHOD', 'GET'),
		'post'    => array('REQUEST_METHOD', 'POST'),
		'put'     => array('REQUEST_METHOD', 'PUT'),
		'delete'  => array('REQUEST_METHOD', 'DELETE'),
		'head'    => array('REQUEST_METHOD', 'HEAD'),
		'options' => array('REQUEST_METHOD', 'OPTIONS')
	);
	
	protected $_autoConfig = array(
		'classes' => 'merge', 'env', 'detectors' => 'merge', 'base', 'type', 'stream'
	);
	protected $_acceptContent = array();
	protected $_locale = null;

	protected function _init() 
	{
		parent::_init();

		$mobile = array(
			'iPhone', 'MIDP', 'AvantGo', 'BlackBerry', 'J2ME', 'Opera Mini', 'DoCoMo', 'NetFront',
			'Nokia', 'PalmOS', 'PalmSource', 'portalmmm', 'Plucker', 'ReqwirelessWeb', 'iPod',
			'SonyEricsson', 'Symbian', 'UP\.Browser', 'Windows CE', 'Xiino', 'Android'
		);
		if(!empty($this->_config['detectors']['mobile'][1]))
			$mobile = array_merge($mobile, (array) $this->_config['detectors']['mobile'][1]);

		$this->_detectors['mobile'][1] = $mobile;
		$defaults = array('REQUEST_METHOD' => 'GET', 'CONTENT_TYPE' => 'text/html');
		$this->_env += (array) $_SERVER + (array) $_ENV + $defaults;
		$envs = array('isapi' => 'IIS', 'cgi' => 'CGI', 'cgi-fcgi' => 'CGI');
		$this->_env['PLATFORM'] = isset($envs[PHP_SAPI]) ? $envs[PHP_SAPI] : null;
		$this->_base = $this->_base();
		$this->url = $this->_url();

		if(!empty($this->_config['query']))
			$this->query = $this->_config['query'];
		if(isset($_GET))
			$this->query += $_GET;
		if(!empty($this->_config['data']))
			$this->data = $this->_config['data'];
		if(isset($_POST))
			$this->data += $_POST;
		if(isset($this->data['_method'])) {
			$this->_env['HTTP_X_HTTP_METHOD_OVERRIDE'] = strtoupper($this->data['_method']);
			unset($this->data['_method']);
		}
		if(!empty($this->_env['HTTP_X_HTTP_METHOD_OVERRIDE']))
			$this->_env['REQUEST_METHOD'] = $this->_env['HTTP_X_HTTP_METHOD_OVERRIDE'];  
			
		$type         = $this->type($this->_env['CONTENT_TYPE']);
		$this->method = $method = strtoupper($this->_env['REQUEST_METHOD']);

		if(!$this->data && ($method == 'POST' || $method == 'PUT')) 
		{
			if($type !== 'html') 
			{
				$this->_stream = $this->_stream ?: fopen('php://input', 'r');
				$media = $this->_classes['media'];
				$this->data = (array) $media::decode($type, stream_get_contents($this->_stream));
				fclose($this->_stream);
			}
		}          
		
		$this->data = Set::merge((array) $this->data, $this->_parseFiles());
	}

	public function __get($name) 
	{
		if(isset($this->params[$name])) 
			return $this->params[$name];
	}

	public function __isset($name) 
	{
		return isset($this->params[$name]);
	}

	public function env($key) 
	{
		if(strtolower($key) == 'base')
			return $this->_base;

		if($key == 'SCRIPT_NAME' && !isset($this->_env['SCRIPT_NAME'])) {
			if($this->_env['PLATFORM'] == 'CGI' || isset($this->_env['SCRIPT_URL'])) 
				$key = 'SCRIPT_URL';
		}

		$val = array_key_exists($key, $this->_env) ? $this->_env[$key] : getenv($key);
		$this->_env[$key] = $val;

		if($key == 'REMOTE_ADDR' && $val == $this->env('SERVER_ADDR')) 
			$val = ($addr = $this->env('HTTP_PC_REMOTE_ADDR')) ? $addr : $val;

		if($val !== null && $val !== false && $key !== 'HTTPS') 
			return $val;

		switch ($key) 
		{
			case 'HTTPS':
				if(isset($this->_env['SCRIPT_URI'])) 
					return (strpos($this->_env['SCRIPT_URI'], 'https://') === 0);
				if(isset($this->_env['HTTPS']))
					return (!empty($this->_env['HTTPS']) && $this->_env['HTTPS'] !== 'off');
				return false;
			case 'SCRIPT_FILENAME':
				if($this->_env['PLATFORM'] == 'IIS')
					return str_replace('\\\\', '\\', $this->env('PATH_TRANSLATED'));

				return $this->env('DOCUMENT_ROOT') . $this->env('PHP_SELF');
			case 'DOCUMENT_ROOT':
				$fileName = $this->env('SCRIPT_FILENAME');
				$offset = (!strpos($this->env('SCRIPT_NAME'), '.php')) ? 4 : 0;
				$offset = strlen($fileName) - (strlen($this->env('SCRIPT_NAME')) + $offset);
				return substr($fileName, 0, $offset);
			case 'PHP_SELF':
				return str_replace('\\', '/', str_replace(
					$this->env('DOCUMENT_ROOT'), '', $this->env('SCRIPT_FILENAME')
				));
			case 'CGI':
			case 'CGI_MODE':
				return ($this->_env['PLATFORM'] == 'CGI');
			case 'HTTP_BASE':
				return preg_replace('/^([^.])*/i', null, $this->_env['HTTP_HOST']);
		}
	}

	public function accepts($type = null) 
	{
		if($type === true)
			return $this->_parseAccept();
		if(!$type && isset($this->params['type']))
			return $this->params['type'];

		$media = $this->_classes['media'];
		return $media::negotiate($this) ?: 'html';
	}

	protected function _parseAccept() 
	{
		if($this->_acceptContent)
			return $this->_acceptContent;

		$accept = $this->env('HTTP_ACCEPT');
		$accept = (preg_match('/[a-z,-]/i', $accept)) ? explode(',', $accept) : array('text/html');

		foreach(array_reverse($accept) as $i => $type) 
    {
			unset($accept[$i]);
			list($type, $q) = (explode(';q=', $type, 2) + array($type, 1.0 + $i / 100));
			$accept[$type] = ($type == '*/*') ? 0.1 : floatval($q);
		}
		arsort($accept, SORT_NUMERIC);

		if(isset($accept['application/xhtml+xml']) && $accept['application/xhtml+xml'] >= 1)
			unset($accept['application/xml']);

		$media = $this->_classes['media'];

		if(isset($this->params['type']) && ($handler = $media::type($this->params['type']))) 
		{
			if(isset($handler['content'])) {
				$type = (array) $handler['content'];
				$accept = array(current($type) => 1) + $accept;
			}
		}         
		
		return $this->_acceptContent = array_keys($accept);
	}

	public function get($key) 
	{
		list($var, $key) = explode(':', $key);

		switch(true) 
		{
			case in_array($var, array('params', 'data', 'query')):
				return isset($this->{$var}[$key]) ? $this->{$var}[$key] : null;
			case ($var === 'env'):
				return $this->env(strtoupper($key));
			case ($var === 'http' && $key === 'method'):
				return $this->env('REQUEST_METHOD');
			case ($var === 'http'):
				return $this->env('HTTP_' . strtoupper($key));
		}
	}

	public function is($flag) 
	{
		$media = $this->_classes['media'];

		if(!isset($this->_detectors[$flag])) 
		{
			if(!in_array($flag, $media::types()))
				return false;

			return $this->type() == $flag;
		}
		$detector = $this->_detectors[$flag];

		if(!is_array($detector) && is_callable($detector)) 
			return $detector($this);
		if(!is_array($detector))
			return (boolean) $this->env($detector);

		list($key, $check) = $detector + array('', '');

		if(is_array($check))
			$check = '/' . join('|', $check) . '/i';
		if(Validator::isRegex($check))
			return (boolean) preg_match($check, $this->env($key));

		return ($this->env($key) == $check);
	}

	public function type($type = null) 
	{
		if($type === null)
			$type = $this->type ?: $this->env('CONTENT_TYPE');

		return parent::type($type);
	}
	
	public function detect($flag, $detector = null) 
	{
		if(is_array($flag))
			$this->_detectors = $flag + $this->_detectors;
		else 
			$this->_detectors[$flag] = $detector;
	}

	public function referer($default = null, $local = false) 
	{
		if($ref = $this->env('HTTP_REFERER')) 
		{
			if(!$local) 
				return $ref;
			if(strpos($ref, '://') == false) 
				return $ref;
		}    
		
		return ($default != null) ? $default : '/';
	}

	public function to($format, array $options = array())
	{
		$defaults = array(
			'scheme' => $this->env('HTTPS') ? 'https' : 'http',
			'host'   => $this->env('HTTP_HOST'),
			'path'   => $this->_base . $this->url,
			'query'  => $this->query
		);  
		
		return parent::to($format, $options + $defaults);
	}

	public function locale($locale = null) 
	{
		if($locale) 
			$this->_locale = $locale;
		if($this->_locale)
			return $this->_locale;

		if(isset($this->params['locale']))
			return $this->params['locale'];
	}

	protected function _base() 
	{
		if(isset($this->_base)) 
			return $this->_base;

		$base = str_replace('\\', '/', dirname($this->env('PHP_SELF')));     
		
		return rtrim(str_replace(array("/app/webroot", '/webroot'), '', $base), '/');
	}

	protected function _url() 
	{
		if(isset($this->_config['url'])) 
			return rtrim($this->_config['url'], '/');
		if(!empty($_GET['url']))
			return rtrim($_GET['url'], '/');
		if($uri = $this->env('REQUEST_URI'))
			return str_replace($this->env('base'), '/', parse_url($uri, PHP_URL_PATH));  
			
		return '/';
	}

	protected function _parseFiles() 
	{
		if(isset($_FILES) && $_FILES) 
		{
			$result = array();

			$normalize = function($key, $value) use ($result, &$normalize)
			{
				foreach($value as $param => $content) 
				{
					foreach($content as $num => $val) 
					{
						if(is_numeric($num)) {
							$result[$key][$num][$param] = $val;
							continue;
						}
						if(is_array($val)) 
						{
							foreach($val as $next => $one) {
								$result[$key][$num][$next][$param] = $one;
							}
							continue;
						}
						$result[$key][$num][$param] = $val;
					}
				}
				return $result;
			};
			foreach($_FILES as $key => $value) 
			{
				if(isset($value['name'])) 
				{
					if(is_string($value['name'])) {
						$result[$key] = $value;
						continue;
					}
					if(is_array($value['name']))
						$result += $normalize($key, $value);
				}
			}  
			
			return $result;
		}      
		
		return array();
	}
}