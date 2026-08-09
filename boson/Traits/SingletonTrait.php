<?php namespace Boson\Traits;
/**
* @name      Boson PHP framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @copyright Copyright (c) 2018 All rights reserved
*/
  
trait SingletonTrait
{
	protected static $_instance;
	
	/**
	* Return object instance of class
	*/
	public static function getInstance()
	{
		if( static::$_instance == null ) {
			static::$_instance = new static();
		}
		
		return static::$_instance;
	}
}
