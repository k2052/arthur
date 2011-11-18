<?php                

namespace arthur\action;

use arthur\util\Set;
use arthur\util\Validator;

class JRequest extends \arthur\action\Request
{
  protected function _init() 
	{
		parent::_init();
		
		$this->_config['url'] = $this->_jurl();     
		$this->url = $this->_url();
	}   
	
	protected function _jurl() 
	{    
	  $url = '';
	  if(isset($this->query['option'])) {
	    if(isset($this->query['view'])) $url .= '/' . $this->query['view'];   
	    if(isset($this->query['action'])) $url .= '/' . $this->query['action'];        
	  }
	  else {  
	    $url = $this->url;
	  }    
	  return $url;
	}                                      
}