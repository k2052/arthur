<?php

$params  = getopt("", array("app::"));
$working = $params ? array_pop($params) : getcwd();
$app     = null;

$bootstrap = function() use ($working) 
{
	define('ARTHUR_LIBRARY_PATH', dirname(dirname(__DIR__)));
	define('ARTHUR_APP_PATH', $working);

	if(!include ARTHUR_LIBRARY_PATH . '/arthur/core/Libraries.php') 
	{
		$message  = "Arthur core could not be found.  Check the value of ARTHUR_LIBRARY_PATH in ";
		$message .= __FILE__ . ".  It should point to the directory containing your ";
		$message .= "/libraries directory.";
		throw new ErrorException($message);
	}

	arthur\core\Libraries::add('arthur');
	arthur\core\Libraries::add(basename($working), array(
		'default' => true,
		'path'    => $working
	));      
};

$run = function() {
	return arthur\console\Dispatcher::run(new arthur\console\Request())->status;
};


if(file_exists("{$working}/config/bootstrap.php"))
	$app = $working;
elseif(file_exists("{$working}/app/config/bootstrap.php"))
	$app = "{$working}/app";

if($app) 
{
	foreach(array("bootstrap.php", "bootstrap/libraries.php") as $file) 
	{
		if(!file_exists($path = "{$app}/config/{$file}"))
			continue;

		if(preg_match("/^define\([\"']ARTHUR_LIBRARY_PATH[\"']/m", file_get_contents($path))) {
			include "{$app}/config/bootstrap.php";
			exit($run());
		}
	}
}

$bootstrap();
$app ? include "{$app}/config/bootstrap.php" : null;
exit($run());