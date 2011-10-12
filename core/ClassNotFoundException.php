<?php

namespace arthur\core;

class ClassNotFoundException extends \RuntimeException 
{
	protected $code = 500;
}