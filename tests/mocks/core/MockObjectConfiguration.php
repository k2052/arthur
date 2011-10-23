<?php

namespace arthur\tests\mocks\core;

class MockObjectConfiguration extends \arthur\core\Object 
{
	protected $_testScalar = 'default';
	protected $_testArray = array('default');
	protected $_protected = null;

	public function __construct(array $config = array()) 
	{
		if(isset($config['autoConfig'])) {
			$this->_autoConfig = (array) $config['autoConfig'];
			unset($config['autoConfig']);
		}      
		
		parent::__construct($config);
	}

	public function testScalar($value) 
	{
		$this->_testScalar = 'called';
	}

	public function getProtected() 
	{
		return $this->_protected;
	}

	public function getConfig() 
	{
		return array(
			'testScalar' => $this->_testScalar,
			'testArray'  => $this->_testArray
		);
	}
}