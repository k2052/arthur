<?php

namespace arthur\console\command;

use Phar;
use Exception;
use RuntimeException;
use arthur\core\Libraries;

class Library extends \arthur\console\Command 
{
	public $conf = null;
	public $path = null;
	public $server = 'lab.lithify.me';
	public $port = 80;
	public $username = '';
	public $password = '';
	public $f = false;
	public $force = false;
	public $filter = '/\.(php|htaccess|jpg|png|gif|css|js|ico|json|ini)|(empty)$/';
	protected $_settings = array();

	protected $_classes = array(
		'service'  => 'arthur\net\http\Service',
		'response' => 'arthur\console\Response'
	);

	protected $_autoConfig = array(
		'classes' => 'merge', 'env', 'detectors' => 'merge', 'base', 'type', 'stream'
	);

	protected function _init() 
	{
		parent::_init(); 
		
		if($this->server)
			$this->_settings['servers'][$this->server] = true;
		if(file_exists($this->conf))
			$this->_settings += (array) json_decode($this->conf, true);

		$this->path  = $this->_toPath($this->path ?: 'libraries');
		$this->force = $this->f ? $this->f : $this->force;
	}

	public function config($key = null, $value = null, $options = true) 
	{
		if(empty($key) || empty($value))
			return $this->_settings;

		switch ($key) 
		{
			case 'server':
				$this->_settings['servers'][$value] = $options;
			break;
		}    
		
		return file_put_contents($this->conf, json_encode($this->_settings));
	}

	public function extract($name = 'new', $result = null) 
	{
		$from = 'app';
		$to  = $name;

		if($result) {
			$from = $name;
			$to   = $result;
		}
		$to = $this->_toPath($to);

		if($from[0] !== '/') 
		{
			$from = Libraries::locate('command.create.template', $from, array(
				'filter' => false, 'type' => 'file', 'suffix' => '.phar.gz'
			));
			if(!$from || is_array($from))
				return false;
		}
		if(file_exists($from)) 
		{
			try {
				$archive = new Phar($from);
			} 
			catch (Exception $e) {
				$this->error($e->getMessage());
				return false;
			}
			if($archive->extractTo($to)) {
				$this->out(basename($to) . " created in " . dirname($to) . " from {$from}");
				return $this->_replaceAfterExtract($to);
			}
		}       
		
		$this->error("Could not extract {$to} from {$from}");
		return false;
	}
	
	protected function _replaceAfterExtract($extracted) 
	{
		$replacements = array(
			'config/bootstrap/libraries.php' => array(
				'define(\'ARTHUR_LIBRARY_PATH\', dirname(ARTHUR_APP_PATH) . \'/libraries\');' =>
					'define(\'ARTHUR_LIBRARY_PATH\', \'' . ARTHUR_LIBRARY_PATH . '\');'
			)
		);

		foreach($replacements as $filename => $definitions) 
		{
			$filepath = $extracted . '/' . $filename; 
			
			if(file_exists($filepath)) 
			{
				$content = file_get_contents($filepath);    
				
				foreach($definitions as $original => $replacement) {
					$content = str_replace($original, $replacement, $content);
				}   
				
				if(!file_put_contents($filepath, $content)) {
					$this->error("Could not replace content in {$filepath}");
					return false;
				}
			}
		}     
		
		return true;
	}

	public function archive($name = null, $result = null) 
	{
		if(ini_get('phar.readonly') == '1')
			throw new RuntimeException('Set `phar.readonly` to `0` in `php.ini`.');

		$from = $name;
		$to   = $name;

		if($result) {
			$from = $name;
			$to   = $result;
		}
		$path = $this->_toPath($to);

		if(file_exists("{$path}.phar")) 
		{
			if(!$this->force) {
				$this->error(basename($path) . ".phar already exists in " . dirname($path));
				return false;
			}
			Phar::unlinkArchive("{$path}.phar");
		}
		try {
	 		$archive = new Phar("{$path}.phar");
		} 
		catch (Exception $e) {
			$this->error($e->getMessage());
			return false;
		}
		$result = null;
		$from   = $this->_toPath($from);

		if(is_dir($from))
			$result = (boolean) $archive->buildFromDirectory($from, $this->filter);
		if(file_exists("{$path}.phar.gz")) 
		{
			if(!$this->force) {
				$this->error(basename($path) . ".phar.gz already exists in " . dirname($path));
				return false;
			}
			Phar::unlinkArchive("{$path}.phar.gz");
		}
		if($result) 
		{
			$archive->compress(Phar::GZ);
			$this->out(basename($path) . ".phar.gz created in " . dirname($path) . " from {$from}");
			return true;
		}   
		
		$this->error("Could not create archive from {$from}");
		return false;
	}

	public function find($type = 'plugins') 
	{
		$results = array();

		foreach($this->_settings['servers'] as $server => $enabled) 
		{
			if(!$enabled) continue;  
			
			$service = $this->_instance('service', array(
				'host' => $server, 'port' => $this->port
			));
			$results[$server] = json_decode($service->get("lab/{$type}.json"));

			if(empty($results[$server])) {
				$this->out("No {$type} at {$server}");
				continue;
			}   
			
			foreach((array) $results[$server] as $data) 
			{
				$name   = isset($data->class) ? $data->class : $data->name;
				$header = "{$server} > {$name}";
				$out    = array(
					"{$data->summary}",
					"Version: {$data->version}",
					"Created: {$data->created}"
				);      
				
				$this->header($header);
				$this->out(array_filter($out));
			}
		}
	}

	public function install($name = null) 
	{
		$results = array();     
		
		foreach($this->_settings['servers'] as $server => $enabled) 
		{
			if(!$enabled) continue; 
			$service = $this->_instance('service', array(
				'host' => $server, 'port' => $this->port
			));      
			
			if($plugin = json_decode($service->get("lab/{$name}.json")))
				break;
		}      
		
		if(empty($plugin->sources)) {
			$this->error("{$name} not found.");
			return false;
		}    
		
		$hasGit = function () 
		{
			return (strpos(shell_exec('git --version'), 'git version') !== false);
		};     
		
		foreach((array) $plugin->sources as $source) 
		{
			if(strpos($source, 'phar.gz') !== false && file_exists($source)) 
			{
				$written = file_put_contents(
					"{$this->path}/{$plugin->name}.phar.gz", file_get_contents($source)
				);
				if(!$written) {
					$this->error("{$plugin->name}.phar.gz could not be saved");
					return false;
				}
				$this->out("{$plugin->name}.phar.gz saved to {$this->path}");

				try 
				{
					$archive = new Phar("{$this->path}/{$plugin->name}.phar.gz");

					if($archive->extractTo("{$this->path}/{$plugin->name}")) 
					{
						$this->out("{$plugin->name} installed to {$this->path}/{$plugin->name}");
						$this->out("Remember to update the bootstrap.");
						return true;
					}
				} 
				catch(Exception $e) {
					$this->error($e->getMessage());
				}
			}
			$url = parse_url($source);

			if(!empty($url['scheme']) && $url['scheme'] == 'git' && $hasGit()) 
			{
				$result = shell_exec(
					"cd {$this->path} && git clone {$source} {$plugin->name}"
				);
				if(is_dir("{$this->path}/{$plugin->name}")) 
				{
					$this->out("{$plugin->name} installed to {$this->path}/{$plugin->name}");
					$this->out("Remember to update the bootstrap.");
					return true;
				}
			}
		}    
		
		$this->out("{$plugin->name} not installed.");
		return false;
	}

	public function formulate($name = null) 
	{
		if(!$name)
			$name = $this->in("please supply a name");

		$result  = false;
		$path    = $this->_toPath($name);
		$name    = basename($path);
		$formula = "{$path}/config/{$name}.json";

		$data = array();

		if(file_exists($formula))
			$data = json_decode(file_get_contents($formula), true);
		if(empty($data['version']))
			$data['version'] = $this->in("please supply a version");
		if(empty($data['summary']))
			$data['summary'] = $this->in("please supply a summary");
		if(file_exists($path) && !file_exists($formula)) 
		{
			$defaults = array(
				'name'        => $name, 'version' => '0.1',
				'summary'     => "a plugin called {$name}",
				'maintainers' => array(array(
					'name' => '', 'email' => '', 'website' => ''
				)),
				'sources'   => array("http://{$this->server}/lab/download/{$name}.phar.gz"),
				'commands'  => array(
					'install' => array(), 'update' => array(), 'remove' => array()
				),
				'requires' => array()
			);
			$data += $defaults;

			if(!is_dir(dirname($formula)) && !mkdir(dirname($formula), 0755, true)) {
				$this->error("Formula for {$name} not created in {$path}");
				return false;
			}
		}    
		
		if(is_dir(dirname($formula)) && file_put_contents($formula, json_encode($data))) {
			$this->out("Formula for {$name} created in {$path}.");
			return true;
		}   
		
		$this->error("Formula for {$name} not created in {$path}");
		return false;
	}

	public function push($name = null) 
	{
		if(!$name)
			$name = $this->in("please supply a name"); 
			
		$path = $this->_toPath($name);
		$name = basename($name);
		$file = "{$path}.phar.gz";

		if(!file_exists("phar://{$file}/config/{$name}.json")) 
		{
			$this->error(array(
				"The forumla for {$name} is missing.", "Run li3 library formulate {$name}"
			));
			return false;
		}
		$formula = json_decode(file_get_contents("phar://{$file}/config/{$name}.json"));
		$isValid = (
			!empty($formula->name) && !empty($formula->version)
			&& !empty($formula->summary) && !empty($formula->sources)
		);
		if(!$isValid) 
		{
			$this->error(array(
				"The forumla for {$name} is not valid.", "Run li3 library formulate {$name}"
			)); 
			
			return false;
		}
		if(file_exists($file)) 
		{
			$service = $this->_instance('service', array(
				'host' => $this->server, 'port' => $this->port,
				'auth' => 'Basic', 'username' => $this->username, 'password' => $this->password
			));
			$boundary = md5(date('r', time()));
			$headers  = array("Content-Type: multipart/form-data; boundary={$boundary}");
			
			$name = basename($file);
			$data = join("\r\n", array(
				"--{$boundary}",
				"Content-Disposition: form-data; name=\"phar\"; filename=\"{$name}\"",
				"Content-Type: application/phar", "",
				base64_encode(file_get_contents($file)),
				"--{$boundary}--"
			));  
			
			$result = json_decode($service->post(
				'/lab/server/receive', $data, compact('headers')
			));

			if($service->last->response->status['code'] == 201) 
			{
				$this->out(array(
					"{$result->name} added to {$this->server}.",
					"See http://{$this->server}/lab/plugins/view/{$result->id}"
				)); 
				
				return $result;
			}   
			
			if(!empty($result->error)) {
				$this->error($result->error);
				return false;
			}   
			
			$this->error((array) $result);
			return false;
		}          
		
		$this->error(array("{$file} does not exist.", "Run li3 library archive {$name}"));
		return false;
	}

	public function update() 
	{
		$this->error('Please implement me');
	}

	protected function _toPath($name = null) 
	{
		if($name && $name[0] === '/')
			return $name;
		$library = Libraries::get($name);

		if(!empty($library['path']))
			return $library['path'];

		$path = $this->request->env('working');
		return (!empty($name)) ? "{$path}/{$name}" : $path;
	}
}