<?php namespace Boson;
/**
* @name      Boson PHP micro framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @link      http://alex-tisch.ru
* @copyright Copyright (c) 2018 All rights reserved
* @version   2.1
*
* Caching in a database table. Stores serialized values with TTL.
* Supports an in-memory cache to reduce the number of queries.
*/

class TableCacheException extends \Exception {};

class TableCache
{
    protected static $table = 'cache';
    protected static $key_has_cache = [];
    
    /**
     * Removes expired records from the DB and resets the in-memory cache for them.
     */
    public static function check()
    {
        if( orm()->hasTable(self::$table) ) {
            $timestamp = time();
            $items     = table(self::$table)->where('expiration', '<', $timestamp)->get();
            
            if( array_count($items) > 0 )  {
                foreach($items as $item) {
                    if( !empty(self::$key_has_cache[$item->key]) ) {
                        unset( self::$key_has_cache[$item->key] );
                    }
                }
                
                table(self::$table)->where('expiration', '<', $timestamp)->delete();
            }
        }
    }
    
    /**
     * Checks whether a key exists (taking expiry into account).
     * The result is cached in memory until the next check().
     */
    public static function has($key)
    {
        if( !orm()->hasTable(self::$table) ) {
            throw new TableCacheException(
                'TableCache table not created. Use TableCache::install() for creating cache table.'
            );
        }
        
        if( !is_array(self::$key_has_cache) ) {
            self::$key_has_cache = [];
        }
        
        if( !empty(self::$key_has_cache[$key]) ) {
            return true;
        }
        
        $item = table(self::$table)->where('key', '=', $key)
                                   ->first();
                       
        if( $item ) {
            self::$key_has_cache[ $key ] = $item;

            return true;
        }
                                    
        return false;
    }
    
    /**
     * Returns the value from the cache (unserialized).
     */
    public static function get($key)
    {
        if( self::has($key) ) {
            return unserialize(
                self::$key_has_cache[ $key ]->value
            );
        }
        
        return null;
    }

    /**
     * Stores a value in the cache.
     *
     * @param string $key     Key
     * @param mixed  $value   Value (or callable for lazy evaluation)
     * @param int    $expire  Lifetime in seconds
     * @return mixed          Stored value
     */
    public static function put($key, $value, $expire = UNIXTIME_HOUR)
    {
        // Evaluate the callable
        if( is_callable($value) && !is_string($value) ) {
            $value = $value();
        }
        
        // Delete the old record if present
        if( self::has($key) ) {
            table(self::$table)->where('key', '=', $key)->delete();
            unset( self::$key_has_cache[$key] );
        }
        
        table(self::$table)->insert([
            'key'        => $key,
            'value'      => serialize($value),
            'expiration' => time() + $expire,
        ]);
        
        return $value;
    }

    /**
     * Returns the value from the cache or computes, stores and returns it.
     */
    public static function remember($key, \Closure $callback, $expire = UNIXTIME_HOUR)
    {
        if( self::has($key) ) {
            return self::get($key);
        }
        
        $value = $callback();
        
        self::put($key, $value, $expire);
        
        return $value;
    }
    
    /**
     * Returns the value and removes it from the cache.
     */
    public static function pull($key)
    {
        if( self::has($key) ) {
            $item = self::$key_has_cache[ $key ];
            
            table(self::$table)->where('key', '=', $key)->delete();
            unset( self::$key_has_cache[$key] );
            
            return unserialize($item->value);
        }
        
        return null;
    }
    
    /**
     * Removes a key from the cache.
     */
    public static function forget($key)
    {
        if( self::has($key) ) {
            table(self::$table)->where('key', '=', $key)->delete();
            unset( self::$key_has_cache[$key] );
        }
    }
    
    /**
     * Completely clears the cache.
     */
    public static function flush()
    {
        if( orm()->hasTable(self::$table) ) {
            table(self::$table)->truncate();
            self::$key_has_cache = [];
        }
    }
    
    /**
     * Creates the cache table.
     */
    public static function install()
    {
        if( !orm()->hasTable(self::$table) ) {
            $table = dbCfg('prefix') . self::$table;
            
            orm()->db()->query("
                CREATE TABLE IF NOT EXISTS `{$table}` (
                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  `key` varchar(255) NOT NULL DEFAULT '',
                  `value` mediumtext,
                  `expiration` bigint(20) unsigned NOT NULL DEFAULT '0',
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `key` (`key`),
                  KEY `expiration` (`expiration`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
            ");
            
            orm()->hasTable(self::$table, 'default', true);
        }
    }
}
