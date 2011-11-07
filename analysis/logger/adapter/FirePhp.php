<?php

namespace arthur\analysis\logger\adapter;

class FirePhp extends \arthur\core\Object 
{
	protected $_headers = array(
		'X-Wf-Protocol-1' => 'http://meta.wildfirehq.org/Protocol/JsonStream/0.2',
		'X-Wf-1-Plugin-1' =>
			'http://meta.firephp.org/Wildfire/Plugin/FirePHP/Library-FirePHPCore/0.3',
		'X-Wf-1-Structure-1' =>
			'http://meta.firephp.org/Wildfire/Structure/FirePHP/FirebugConsole/0.1'
	);

	protected $_levels = array(
		'emergency' => 'ERROR',
		'alert'     => 'ERROR',
		'critical'  => 'ERROR',
		'error'     => 'ERROR',
		'warning'   => 'WARN',
		'notice'    => 'INFO',
		'info'      => 'INFO',
		'debug'     => 'LOG'
	);

	protected $_counter = 1;
	protected $_response = null;
	protected $_queue = array();

	public function bind($response) 
	{
		$this->_response = $response;
		$this->_response->headers += $this->_headers;

		foreach($this->_queue as $message) {
			$this->_write($message);
		}
	}

	public function write($priority, $message) 
	{
		$_self =& $this;

		return function($self, $params) use (&$_self) 
		{
			$priority = $params['priority'];
			$message  = $params['message'];
			$message  = $_self->invokeMethod('_format', array($priority, $message));
			$_self->invokeMethod('_write', array($message));
			return true;
		};
	}

	protected function _write($message) 
	{
		if(!$this->_response)
			return $this->_queue[] = $message;

		$this->_response->headers[$message['key']] = $message['content'];
	}

	protected function _format($type, $message) 
	{
		$key = 'X-Wf-1-1-1-' . $this->_counter++;

		$content = array(array('Type' => $this->_levels[$type]), $message);
		$content = json_encode($content);
		$content = strlen($content) . '|' . $content . '|';

		return compact('key', 'content');
	}
}