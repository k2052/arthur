<?php

namespace arthur\template\view;

use arthur\core\Libraries;
use arthur\template\TemplateException;

class Compiler extends \arthur\core\StaticObject 
{
	protected static $_processors = array(
		'/\<\?=\s*\$this->(.+?)\s*;?\s*\?>/msx' => '<?php echo $this->$1; ?>',
		'/\<\?=\s*(\$h\(.+?)\s*;?\s*\?>/msx' => '<?php echo $1; ?>',
		'/\<\?=\s*(.+?)\s*;?\s*\?>/msx' => '<?php echo $h($1); ?>'
	);

	public static function template($file, array $options = array()) 
	{
		$cachePath = Libraries::get(true, 'resources') . '/tmp/cache/templates';
		$defaults  = array('path' => $cachePath, 'fallback' => true);
		$options  += $defaults;

		$stats    = stat($file);
		$dir      = dirname($file);
		$oname    = basename(dirname($dir)) . '_' . basename($dir) . '_' . basename($file, '.php');
		$template = "template_{$oname}_{$stats['ino']}_{$stats['mtime']}_{$stats['size']}.php";
		$template = "{$options['path']}/{$template}";

		if(file_exists($template))
			return $template;
		$compiled = static::compile(file_get_contents($file));

		if(is_writable($cachePath) && file_put_contents($template, $compiled) !== false) 
		{
			foreach(glob("{$options['path']}/template_{$oname}_*.php") as $expired) {
				if($expired !== $template)
					unlink($expired);
			}      
			
			return $template;
		}
		if($options['fallback'])
			return $file;     
			
		throw new TemplateException("Could not write compiled template `{$template}` to cache.");
	}

	public static function compile($string) 
	{
		$patterns = static::$_processors;    
		
		return preg_replace(array_keys($patterns), array_values($patterns), $string);
	}
}