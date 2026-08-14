<?php
/**
* @name      Boson PHP micro framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @link      http://alex-tisch.ru
* @copyright Copyright (c) 2018 All rights reserved
*/

// -----------------------------------------------------------------------------
  /**
  * Aliases
  */
  function input($parameter_name = null, $default = null)
  {
	  return ($parameter_name === null) ? \Boson\Input::getInstance() : \Boson\Input::getInstance()->input($parameter_name, $default);
  }
// -----------------------------------------------------------------------------
  function session(): \Boson\Session
  {
	  return \Boson\Session::getInstance();
  }
// -----------------------------------------------------------------------------
  function cookies(): \Boson\Cookies
  {
	  return \Boson\Cookies::getInstance();
  }
// -----------------------------------------------------------------------------
  function router(): \Boson\MicroRouter
  {
	  return \Boson\MicroRouter::getInstance();
  }
// -----------------------------------------------------------------------------
  function app(): \Boson\App
  {
	  return \Boson\App::getInstance();
  }
// -----------------------------------------------------------------------------
  function validator(array $values, array $rules): \Boson\Validator
  {
	  return new \Boson\Validator($values, $rules);
  }
// -----------------------------------------------------------------------------
  function i18n($key = null, array $values = [])
  {
	  if( !empty($key) ) {
		  return \Boson\I18n::getInstance()->get($key, $values);
	  }
	  
	  return \Boson\I18n::getInstance();
  }
// -----------------------------------------------------------------------------
  function dbCfg($key = null)
  {
	  $config = cfg('database');

	  if( !is_object($config) ) {
		  return null;
	  }

	  // Resolve the connection section the same way Eloquent does:
	  // the "default" section if present, otherwise the first registered one.
	  if( $config->has('default') ) {
		  $section = $config->get('default');
	  } else {
		  $sections = $config->toArray();
		  $section  = !empty($sections) ? reset($sections) : null;
	  }

	  if( $key === null ) {
		  return $section;
	  }

	  if( $section instanceof \Boson\Abstracts\Registry ) {
		  return $section->has($key) ? $section->get($key) : null;
	  }

	  if( is_array($section) ) {
		  return $section[ $key ] ?? null;
	  }

	  return null;
  }
// -----------------------------------------------------------------------------
  function cache($key, $value = null, $expire = UNIXTIME_HOUR)
  {
	  if( !empty($key) && $value !== null ) {
		  return \Boson\TableCache::put($key, $value, $expire);
	  }
	  
	  return \Boson\TableCache::pull($key);
  }
// -----------------------------------------------------------------------------
  function cacheRemember($key, \Closure $callback, $expire = UNIXTIME_HOUR)
  {
	  return \Boson\TableCache::remember($key, $callback, $expire);
  }
  
  function orm(): \Boson\Eloquent
  {
      return \Boson\Eloquent::getInstance();
  }
  
  function db($connection = null): \Illuminate\Database\Connection
  {
      return orm()->db($connection);
  }
  
  function table($table, $connection = null): \Illuminate\Database\Query\Builder
  {
      return db($connection)->table($table);
  }
  
  function schema($connection = null): \Illuminate\Database\Schema\Builder
  {
      return orm()->schema($connection);  
  }
  
// -----------------------------------------------------------------------------
function smarty_function_i18n(array $params, \Smarty\Template $template): string
{
    if (empty($params['str'])) {
        return '';
    }

    $translated = i18n($params['str']);

    if (!empty($params['mod'])) {
        $modifier = "str_{$params['mod']}";

        if (function_exists($modifier)) {
            return (string) $modifier($translated);
        }

        if (function_exists($params['mod'])) {
            return (string) $params['mod']($translated);
        }
    }

    return (string) $translated;
}

function smarty_function_num2word(array $params, \Smarty\Template $template): string
{
    if (!array_key_exists('number', $params) || empty($params['words']) || !is_array($params['words'])) {
        return '%num2word_error%';
    }

    return (string) num2word($params['number'], $params['words']);
}  
