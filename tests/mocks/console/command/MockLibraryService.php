<?php

namespace arthur\tests\mocks\console\command;

use arthur\core\Libraries;
use arthur\net\http\Response;

class MockLibraryService extends \arthur\net\http\Service 
{
	public function send($method, $path = null, $data = array(), array $options = array()) 
	{
		if($this->_config['host'] == 'localhost')
			return null;
		if($method == 'post') 
		{
			$this->request = $this->_request($method, $path, $data, $options);
			if(!empty($this->request->username)) 
			{
				$user = array(
					'method' => 'Basic', 'username' => 'gwoo', 'password' => 'password'
				);
				if($this->request->username !== $user['username']) 
				{
					$this->last = (object) array('response' =>  new Response());
					$this->last->response->status(401);
					return json_encode(array(
						'error' => 'Invalid username/password.'
					));
				}
			}
			$this->last = (object) array('response' =>  new Response());
			$this->last->response->status(201);
			return json_encode($this->__data('plugins', 1));
		}
		if($path == 'lab/plugins')
			return json_encode($this->__data('plugins'));
		if($path == 'lab/extensions')
			return json_encode($this->__data('extensions'));
		if(preg_match("/lab\/plugins/", $path, $match))
			return json_encode($this->__data('plugins'));
		if(preg_match("/lab\/extensions/", $path, $match)) 
			return json_encode($this->__data('extensions'));
		if(preg_match("/lab\/art_lab.json/", $path, $match))
			return json_encode($this->__data('plugins', 0));
		if(preg_match("/lab\/library_test_plugin.json/", $path, $match))
			return json_encode($this->__data('plugins', 1));
		if(preg_match("/lab\/art_docs.json/", $path, $match))
			return json_encode($this->__data('plugins', 2));
	}

	private function __data($type, $key = null) 
	{
		$resources = Libraries::get(true, 'resources');

		$plugins = array(
			array(
				'name'        => 'art_lab', 'version' => '1.0',
				'summary'     => 'the art plugin client/server',
				'maintainers' => array(
					array(
						'name'    => 'gwoo', 'email' => 'gwoo@nowhere.com',
						'website' => 'art.rad-dev.org'
					)
				),
				'created'  => '2009-11-30', 'updated' => '2009-11-30',
				'rating'   => '9.9', 'downloads' => '1000',
				'sources'  => array(
					'git'  => 'git://rad-dev.org/art_lab.git',
					'phar' => 'http://downloads.rad-dev.org/art_lab.phar.gz'
				),
				'requires' => array()
			),
			array(
				'id'      => 'b22a2f0dfc873fd0e1a7655f4895872ae4b94ef4',
				'name'    => 'library_test_plugin', 'version' => '1.0',
				'summary' => 'an art plugin example',
				'maintainers' => array(
					array(
						'name'    => 'gwoo', 'email' => 'gwoo@nowhere.com',
						'website' => 'art.rad-dev.org'
					)
				),
				'created' => '2009-11-30', 'updated' => '2009-11-30',
				'rating'  => '9.9', 'downloads' => '1000',
				'sources' => array(
					'phar' =>  "{$resources}/tmp/tests/library_test_plugin.phar.gz"
				),
				'requires' => array(
					'art_lab' => array('version' => '<=1.0')
				)
			),
			array(
				'name'        => 'art_docs', 'version' => '1.0',
				'summary'     => 'the art plugin client/server',
				'maintainers' => array(
					array(
						'name'    => 'gwoo', 'email' => 'gwoo@nowhere.com',
						'website' => 'art.rad-dev.org'
					)
				),
				'created' => '2009-11-30', 'updated' => '2009-11-30',
				'rating'  => '9.9', 'downloads' => '1000',
				'sources' => array(
					'git'  => 'git://rad-dev.org/art_docs.git',
					'phar' => 'http://downloads.rad-dev.org/art_docs.phar.gz'
				),
				'requires' => array()
			)
		);

		$extensions = array(
			array(
				'class'       => 'Example', 'namespace' => 'app\extensions\adapter\cache',
				'summary'     => 'the example adapter',
				'maintainers' => array(
					array(
						'name'    => 'gwoo', 'email' => 'gwoo@nowhere.com',
						'website' => 'art.rad-dev.org'
					)
				),
				'created' => '2009-11-30', 'updated' => '2009-11-30',
				'rating'  => '9.9', 'downloads' => '1000'
			),
			array(
				'class'       => 'Paginator', 'namespace' => 'app\extensions\helpes',
				'summary'     => 'a paginator helper',
				'maintainers' => array(
					array(
						'name'    => 'gwoo', 'email' => 'gwoo@nowhere.com',
						'website' => 'art.rad-dev.org'
					)
				),
				'created' => '2009-11-30', 'updated' => '2009-11-30',
				'rating'  => '9.9', 'downloads' => '1000'
			)
		);
		$data = compact('plugins', 'extensions');

		if(isset($data[$type][$key]))
			return $data[$type][$key];
		if(isset($data[$type]))
			return $data[$type];
		if($key !== null) 
			return null;

		return $data;
	}
}

?>