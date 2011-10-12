<?php

namespace arthur\net\http;

class Response extends \arthur\net\http\Message 
{
	public $status = array('code' => 200, 'message' => 'OK');
	public $type = 'text/html';
	public $encoding = 'UTF-8';
	
	protected $_statuses = array(
		100 => 'Continue',
		101 => 'Switching Protocols',
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		307 => 'Temporary Redirect',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Time-out',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Large',
		415 => 'Unsupported Media Type',
		416 => 'Requested range not satisfiable',
		417 => 'Expectation Failed',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Time-out'
	);

	public function __construct(array $config = array()) 
	{
		$defaults = array('message' => null);
		$config += $defaults;
		parent::__construct($config);
	}

	protected function _init() 
	{
		parent::_init();

		if($this->_config['message']) 
			$this->body = $this->_parseMessage($this->_config['message']);   
			
		if(isset($this->headers['Content-Type'])) 
		{
			$pattern = '/([-\w\/+]+)(;\s*?charset=(.+))?/i';
			preg_match($pattern, $this->headers['Content-Type'], $match);

			if(isset($match[1]))
				$this->type = trim($match[1]);
			if(isset($match[3]))
				$this->encoding = strtoupper(trim($match[3]));
		}
		if(isset($this->headers['Transfer-Encoding']))
			$this->body = $this->_decode($this->body);
	}

	protected function _parseMessage($body) 
	{
		if(!($parts = explode("\r\n\r\n", $body, 2)) || count($parts) == 1)
			return trim($body);

		list($headers, $body) = $parts;
		$headers = str_replace("\r", "", explode("\n", $headers));

		if(array_filter($headers) == array())
			return trim($body);
		preg_match('/HTTP\/(\d+\.\d+)\s+(\d+)\s+(.*)/i', array_shift($headers), $match);
		$this->headers($headers);

		if(!$match) return trim($body);
		
		list($line, $this->version, $code, $message) = $match;
		$this->status = compact('code', 'message') + $this->status;
		$this->protocol = "HTTP/{$this->version}";       
		
		return $body;
	}

	public function status($key = null, $data = null) 
	{
		if($data === null)
			$data = $key;

		if($data) 
		{
			$this->status = array('code' => null, 'message' => null);

			if(is_numeric($data) && isset($this->_statuses[$data])) {
				$this->status = array('code' => $data, 'message' => $this->_statuses[$data]);
			} 
			else 
			{
				$statuses = array_flip($this->_statuses);

				if(isset($statuses[$data]))
					$this->status = array('code' => $statuses[$data], 'message' => $data);
			}
		}
		if(!isset($this->_statuses[$this->status['code']])) 
			return false;
		if(isset($this->status[$key])) 
			return $this->status[$key];

		return "{$this->protocol} {$this->status['code']} {$this->status['message']}";
	}

	public function __toString() 
	{
		if($this->type != 'text/html' && !isset($this->headers['Content-Type']))
			$this->headers['Content-Type'] = $this->type;

		$first = "{$this->protocol} {$this->status['code']} {$this->status['message']}";
		$response = array($first, join("\r\n", $this->headers()), "", $this->body());
		
		return join("\r\n", $response);
	}

	protected function _decode($body) 
	{
		if(stripos($this->headers['Transfer-Encoding'], 'chunked') === false)
			return $body;

		$stream = fopen('data://text/plain,' . $body, 'r');
		stream_filter_append($stream, 'dechunk');      
		
		return trim(stream_get_contents($stream));
	}
}