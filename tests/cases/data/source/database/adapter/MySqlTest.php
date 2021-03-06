<?php

namespace arthur\tests\cases\data\source\database\adapter;

use arthur\data\Connections;
use arthur\data\model\Query;
use arthur\data\source\database\adapter\MySql;
use arthur\tests\mocks\data\source\database\adapter\MockMySql;

class MySqlTest extends \arthur\test\Unit 
{
	protected $_dbConfig = array();
	public $db = null;
	
	public function skip() 
	{
		$this->skipIf(!MySql::enabled(), 'MySQL Extension is not loaded');

		$this->_dbConfig = Connections::get('test', array('config' => true));
		$hasDb   = (isset($this->_dbConfig['adapter']) && $this->_dbConfig['adapter'] == 'MySql');
		$message = 'Test database is either unavailable, or not using a MySQL adapter';
		$this->skipIf(!$hasDb, $message);

		$this->db = new MySql($this->_dbConfig);

		$arthur = ARTHUR_LIBRARY_PATH . '/arthur';
		$sqlFile = $arthur . '/tests/mocks/data/source/database/adapter/mysql_companies.sql';
		$sql     = file_get_contents($sqlFile);
		$this->db->read($sql, array('return' => 'resource'));
	}

	public function testConstructorDefaults() 
	{
		$db     = new MockMySql(array('autoConnect' => false));
		$result = $db->get('_config');  
		
		$expected = array(
			'autoConnect' => false, 'encoding' => null,'persistent' => true,
			'host'        => 'localhost:3306', 'login' => 'root', 'password' => '',
			'database'    => null, 'init' => true
		);
		$this->assertEqual($expected, $result);
	}

	public function testDatabaseConnection() 
	{
		$db = new MySql(array('autoConnect' => false) + $this->_dbConfig);

		$this->assertTrue($db->connect());
		$this->assertTrue($db->isConnected());

		$this->assertTrue($db->disconnect());
		$this->assertFalse($db->isConnected());

		$db = new MySQL(array(
			'autoConnect' => false, 'encoding' => null,'persistent' => false,
			'host'        => 'localhost:3306', 'login' => 'garbage', 'password' => '',
			'database'    => 'garbage', 'init' => true
		) + $this->_dbConfig);

		$this->expectException();
		$this->assertFalse($db->connect());
		$this->assertFalse($db->isConnected());

		$this->assertTrue($db->disconnect());
		$this->assertFalse($db->isConnected());
	}

	public function testDatabaseEncoding() 
	{
		$this->assertTrue($this->db->isConnected());
		$this->assertTrue($this->db->encoding('utf8'));
		$this->assertEqual('UTF-8', $this->db->encoding());

		$this->assertTrue($this->db->encoding('UTF-8'));
		$this->assertEqual('UTF-8', $this->db->encoding());
	}

	public function testValueByIntrospect() 
	{
		$expected = "'string'";
		$result   = $this->db->value("string");
		$this->assertTrue(is_string($result));
		$this->assertEqual($expected, $result);

		$expected = "'\'this string is escaped\''";
		$result   = $this->db->value("'this string is escaped'");
		$this->assertTrue(is_string($result));
		$this->assertEqual($expected, $result);

		$this->assertIdentical(1, $this->db->value(true));
		$this->assertIdentical(1, $this->db->value('1'));
		$this->assertIdentical(1.1, $this->db->value('1.1'));
	}

	public function testColumnAbstraction() 
	{
		$result = $this->db->invokeMethod('_column', array('varchar'));
		$this->assertIdentical(array('type' => 'string'), $result);

		$result = $this->db->invokeMethod('_column', array('tinyint(1)'));
		$this->assertIdentical(array('type' => 'boolean'), $result);

		$result = $this->db->invokeMethod('_column', array('varchar(255)'));
		$this->assertIdentical(array('type' => 'string', 'length' => 255), $result);

		$result = $this->db->invokeMethod('_column', array('text'));
		$this->assertIdentical(array('type' => 'text'), $result);

		$result = $this->db->invokeMethod('_column', array('text'));
		$this->assertIdentical(array('type' => 'text'), $result);

		$result = $this->db->invokeMethod('_column', array('decimal(12,2)'));
		$this->assertIdentical(array('type' => 'float', 'length' => 12, 'precision' => 2), $result);

		$result = $this->db->invokeMethod('_column', array('int(11)'));
		$this->assertIdentical(array('type' => 'integer', 'length' => 11), $result);
	}

	public function testRawSqlQuerying() 
	{
		$this->assertTrue($this->db->create(
			'INSERT INTO companies (name, active) VALUES (?, ?)',
			array('Test', 1)
		));

		$result = $this->db->read('SELECT * From companies AS Company WHERE name = {:name}', array(
			'name'   => 'Test',
			'return' => 'array'
		));
		$this->assertEqual(1, count($result));
		$expected = array('id', 'name', 'active', 'created', 'modified');
		$this->assertEqual($expected, array_keys($result[0]));

		$this->assertTrue(is_numeric($result[0]['id']));
		unset($result[0]['id']);

		$expected = array('name' => 'Test', 'active' => '1', 'created' => null, 'modified' => null);
		$this->assertIdentical($expected, $result[0]);

		$this->assertTrue($this->db->delete('DELETE From companies WHERE name = {:name}', array(
			'name' => 'Test'
		)));

		$result = $this->db->read('SELECT * From companies AS Company WHERE name = {:name}', array(
			'name'   => 'Test',
			'return' => 'array'
		));
		$this->assertFalse($result);
	}

	public function testAbstractColumnResolution() { }

	public function testExecuteException() 
	{
		$this->expectException();
		$this->db->read('SELECT deliberate syntax error');
	}

	public function testEnabledFeatures() 
	{
		$this->assertTrue(MySql::enabled());
		$this->assertTrue(MySql::enabled('relationships'));
		$this->assertFalse(MySql::enabled('arrays'));
	}

	public function testEntityQuerying()
	{
		$sources = $this->db->sources();
		$this->assertTrue(is_array($sources));
		$this->assertFalse(empty($sources));
	}

	public function testQueryOrdering() 
	{
		$insert = new Query(array(
			'type'   => 'create',
			'source' => 'companies',
			'data' => array(
				'name'    => 'Foo',
				'active'  => true,
				'created' => date('Y-m-d H:i:s')
			)
		));
		$this->assertIdentical(true, $this->db->create($insert));

		$insert->data(array(
			'name'    => 'Bar',
			'created' => date('Y-m-d H:i:s', strtotime('-5 minutes'))
		));
		$this->assertIdentical(true, $this->db->create($insert));

		$insert->data(array(
			'name'    => 'Baz',
			'created' => date('Y-m-d H:i:s', strtotime('-10 minutes'))
		));
		$this->assertIdentical(true, $this->db->create($insert));

		$read = new Query(array(
			'type'   => 'read',
			'source' => 'companies',
			'fields' => array('name'),
			'order'  => array('created' => 'asc')
		));
		$result = $this->db->read($read, array('return' => 'array'));
		$expected = array(
			array('name' => 'Baz'),
			array('name' => 'Bar'),
			array('name' => 'Foo')
		);
		$this->assertEqual($expected, $result);

		$read->order(array('created' => 'desc'));
		$result = $this->db->read($read, array('return' => 'array'));
		$expected = array(
			array('name' => 'Foo'),
			array('name' => 'Bar'),
			array('name' => 'Baz')
		);
		$this->assertEqual($expected, $result);

		$delete = new Query(array('type' => 'delete', 'source' => 'companies'));
		$this->assertTrue($this->db->delete($delete));
	}

	public function testDeletesWithoutAliases() 
	{
		$delete = new Query(array('type' => 'delete', 'source' => 'companies'));
		$this->assertTrue($this->db->delete($delete));
	}

	public function testDescribe() 
	{
		$result = $this->db->describe('companies');
		$expected = array(
			'id'       => array('type' => 'integer', 'length' => 11, 'null' => false, 'default' => null),
			'name'     => array('type' => 'string', 'length' => 255, 'null' => true, 'default' => null),
			'active'   => array('type' => 'boolean', 'null' => true, 'default' => null),
			'created'  => array('type' => 'datetime', 'null' => true, 'default' => null),
			'modified' => array('type' => 'datetime', 'null' => true, 'default' => null)
		);
		$this->assertEqual($expected, $result);
	}
}