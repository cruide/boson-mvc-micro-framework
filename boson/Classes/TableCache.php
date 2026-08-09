<?php namespace Boson;
/**
* @name      Boson PHP micro framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @link      http://alex-tisch.ru
* @copyright Copyright (c) 2018 All rights reserved
* @version   2.1
*
* Кеширование в таблице БД. Хранит сериализованные значения с TTL.
* Поддерживает in-memory кеш для снижения числа запросов.
*/

class TableCacheException extends \Exception {};

class TableCache
{
    protected static $table = 'cache';
    protected static $key_has_cache = [];
    
    /**
     * Удаляет просроченные записи из БД и сбрасывает in-memory кеш для них.
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
     * Проверяет существование ключа (с учётом срока действия).
     * Результат кешируется в памяти до следующей check().
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
     * Возвращает значение из кеша (десериализованное).
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
     * Сохраняет значение в кеш.
     *
     * @param string $key     Ключ
     * @param mixed  $value   Значение (или callable для ленивого вычисления)
     * @param int    $expire  Время жизни в секундах
     * @return mixed          Сохранённое значение
     */
    public static function put($key, $value, $expire = UNIXTIME_HOUR)
    {
        // Вычисляем callable
        if( is_callable($value) && !is_string($value) ) {
            $value = $value();
        }
        
        // Удаляем старую запись если есть
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
     * Возвращает значение из кеша или вычисляет, сохраняет и возвращает его.
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
     * Возвращает значение и удаляет его из кеша.
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
     * Удаляет ключ из кеша.
     */
    public static function forget($key)
    {
        if( self::has($key) ) {
            table(self::$table)->where('key', '=', $key)->delete();
            unset( self::$key_has_cache[$key] );
        }
    }
    
    /**
     * Полная очистка кеша.
     */
    public static function flush()
    {
        if( orm()->hasTable(self::$table) ) {
            table(self::$table)->truncate();
            self::$key_has_cache = [];
        }
    }
    
    /**
     * Создаёт таблицу для кеша.
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
