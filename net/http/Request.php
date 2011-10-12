<?php

namespace arthur\net\http;

use arthurm\util\String;

class Request extends \arthur\net\http\Message
{
	public $query = array();
	public $auth = null;
	public $method = 'GET';
	public $cookies = array();

	/**
	 * Adds config values to the public properties when a new object is created.
	 *
	 * @param array $config Configuration options : default value
	 * - `scheme`: http
	 * - `host`: localhost
	 * - `port`: null
	 * - `username`: null
	 * - `password`: null
	 * - `path`: null
	 * - `query`: array - after the question mark ?
	 * - `fragment`: null - after the hashmark #
	 * - `auth` - the Authorization method (Basic|Digest)
	 * - `method` - GET
	 * - `version`: 1.1
	 * - `headers`: array
	 * - `body`: null
	 */
	public function __construct(array $config = array()) 
	{
		$defaults = array(
			'scheme'   => 'http',
			'host'     => 'localhost',
			'port'     => null,
			'username' => null,
			'password' => null,
			'path'     => null,
			'query'    => array(),
			'fragment' => null,
			'headers'  => array(),
			'body'     => null,
			'auth'     => null,
			'method'   => 'GET'
		);
		$config += $defaults;
		parent::__construct($config);

		$this->headers = array(
			'Host' => $this->port ? "{$this->host}:{$this->port}" : $this->host,
			'Connection' => 'Close',
			'User-Agent' => 'Mozilla/5.0'
		);     
		
		$this->headers($config['headers']);
	}

	public function queryString($params = array(), $format = null) 
	{
		$params = empty($params) ? (array) $this->query : (array) $this->query + (array) $params;
		$params = array_filter($params);

		if(empty($params)) return null;
		if(!$format)
			return "?" . http_build_query($params);

		$query = null;

		foreach($params as $key => $value) 
		{
			if(is_array($value)) 
			{
				foreach($value as $val) {
					$values = array('key' => urlencode("{$key}[]"), 'value' => urlencode($val));
					$query .= String::insert($format, $values);
				}
				continue;
			}      
			
			$values = array('key' => urlencode($key), 'value' => urlencode($value));
			$query .= String::insert($format, $values);
		}     
		
		return "?" . substr($query, 0, -1);
	}

	public function to($format, array $options = array()) 
	{
		$defaults = array(
			'method'  => $this->method,
			'scheme'  => $this->scheme,
			'host'    => $this->host,
			'port'    => $this->port ? ":{$this->port}" : '',
			'path'    => $this->path,
			'query'   => null,
			'auth'    => $this->_config['auth'],
			'headers' => array(),
			'body'    => null,
			'version' => $this->version,
			'ignore_errors' => isset($this->_config['ignore_errors'])
				? $this->_config['ignore_errors'] : true,
			'follow_location' => isset($this->_config['follow_location'])
				? $this->_config['follow_location'] : true
		);
		$options += $defaults;

		switch($format) 
		{
			case 'url':
				$options['query'] = $this->queryString($options['query']);
				return String::insert("{:scheme}://{:host}{:port}{:path}{:query}", $options);
			case 'context':
				if($options['auth']) {
					$auth = base64_encode("{$this->username}:{$this->password}");
					$this->headers('Authorization', "{$options['auth']} {$auth}");
				}
				$body = $this->body($options['body']);
				$this->headers('Content-Length', strlen($body));
				$base = array(
					'content' => $body,
					'method' => $options['method'],
					'header' => $this->headers($options['headers']),
					'protocol_version' => $options['version'],
					'ignore_errors' => $options['ignore_errors'],
					'follow_location' => $options['follow_location']
				);
				return array('http' => array_diff_key($options, $defaults) + $base);
			default:
				return parent::to($format, $options);
		}
	}

	public function __toString() 
	{
		if(!empty($this->_config['auth'])) {
			$this->headers('Authorization', "{$this->_config['auth']} " . base64_encode(
				"{$this->username}:{$this->password}"
			));
		}
		$path = str_replace('//', '/', $this->path) . $this->queryString();
		$body = $this->body();
		$this->headers('Content-Length', strlen($body));

		$status = "{$this->method} {$path} {$this->protocol}";      
		
		return join("\r\n", array($status, join("\r\n", $this->headers()), "", $body));
	}
}