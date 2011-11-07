<?php

namespace arthur\analysis\logger\adapter;

use arthur\util\Inflector;
use arthur\core\NetworkException;

class Growl extends \arthur\core\Object 
{
	protected $_priorities = array(
		'emergency' => 2,
		'alert'     => 1,
		'critical'  => 1,
		'error'     => 1,
		'warning'   => 0,
		'notice'    => -1,
		'info'      => -2,
		'debug'     => -2
	);

	const PROTOCOL_VERSION = 1;
	const TYPE_REG = 0;
	const TYPE_NOTIFY = 1;
	protected $_connection = null;
	protected $_registered = false;
	protected $_autoConfig = array('connection', 'registered');
 
	public function __construct(array $config = array()) 
	{
		$name = basename(ARTHUR_APP_PATH);

		$defaults = compact('name') + array(
			'host'          => '127.0.0.1',
			'port'          => 9887,
			'password'      => null,
			'protocol'      => 'udp',
			'title'         => Inflector::humanize($name),
			'notifications' => array('Errors', 'Messages'),
			'registered'    => false
		);
		parent::__construct($config + $defaults);
	}

	public function write($type, $message, array $options = array()) 
	{
		$_self =& $this;
		$_priorities = $this->_priorities;

		return function($self, $params) use (&$_self, $_priorities) 
		{
			$priority = 0;
			$options = $params['options'];

			if(isset($options['priority']) && isset($_priorities[$options['priority']]))
				$priority = $_priorities[$options['priority']];

			return $_self->notify($params['message'], compact('priority') + $options);
		};
	}

	public function notify($description = '', $options = array()) 
	{
		$this->_register();

		$defaults = array('sticky' => false, 'priority' => 0, 'type' => 'Messages');
		$options += $defaults + array('title' => $this->_config['title']);
		$type     = $options['type'];
		$title    = $options['title'];

		$message = compact('type', 'title', 'description') + array('app' => $this->_config['name']);
		$message = array_map('utf8_encode', $message);

    $flags = ($options['priority'] & 7) * 2;
		$flags = ($options['priority'] < 0) ? $flags |= 8 : $flags;
		$flags = ($options['sticky']) ? $flags | 256 : $flags;

		$params  = array('c2n5', static::PROTOCOL_VERSION, static::TYPE_NOTIFY, $flags);
		$lengths = array_map('strlen', $message);

		$data  = call_user_func_array('pack', array_merge($params, $lengths));
		$data .= join('', $message);
		$data .= pack('H32', md5($data . $this->_config['password']));

		$this->_send($data);
		return true;
	}

	protected function _register() 
	{
		if($this->_registered)
			return true;

		$ct      = count($this->_config['notifications']);
		$app     = utf8_encode($this->_config['name']);
		$nameEnc = $defaultEnc = '';

		foreach($this->_config['notifications'] as $i => $name) {
			$name        = utf8_encode($name);
			$nameEnc    .= pack('n', strlen($name)) . $name;
			$defaultEnc .= pack('c', $i);
		}
		$data     = pack('c2nc2', static::PROTOCOL_VERSION, static::TYPE_REG, strlen($app), $ct, $ct);
		$data    .= $app . $nameEnc . $defaultEnc;
		$checksum = pack('H32', md5($data . $this->_config['password']));
		$data    .= $checksum;

		$this->_send($data);
		return $this->_registered = true;
	}

	protected function _connection() 
	{
		if($this->_connection)
			return $this->_connection;

		$host = "{$this->_config['protocol']}://{$this->_config['host']}";

		if($this->_connection = fsockopen($host, $this->_config['port'], $message, $code))
			return $this->_connection;

		throw new NetworkException("Growl connection failed: (`{$code}`) `{$message}`.");
	}

	protected function _send($data) 
	{
		if(fwrite($this->_connection(), $data, strlen($data)) === false)
			throw new NetworkException('Could not send registration to Growl Server.');

		return true;
	}

	public function __destruct()
	{
		if(is_resource($this->_connection)) {
			fclose($this->_connection);
			unset($this->_connection);
		}
	}
}