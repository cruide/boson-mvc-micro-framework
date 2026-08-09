<?php namespace Boson;
/**
* @name      Boson PHP micro framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @link      http://alex-tisch.ru
* @copyright Copyright (c) 2018 All rights reserved
*/

use Boson\Traits\SingletonTrait;

class Eloquent
{
    use SingletonTrait;
    
    protected $orm;
    protected $cfg;
    protected $tables;
    protected $fields;
    
    public function __construct()
    {
        $this->cfg = cfg('database');
        $this->orm = new \Illuminate\Database\Capsule\Manager();
        
        if( !empty($this->cfg->default) ) {
            foreach($this->cfg as $db_name=>$db_cfg) {
                $this->orm->addConnection([
                    'driver'    => !empty($db_cfg['driver']) ? $db_cfg['driver'] : 'mysql',
                    'host'      => $db_cfg['host'],
                    'port'      => !empty($db_cfg['port']) ? $db_cfg['port'] : 3306,
                    'database'  => $db_cfg['database'],
                    'username'  => $db_cfg['username'],
                    'password'  => $db_cfg['password'],
                    'charset'   => !empty($db_cfg['charset']) ? $db_cfg['charset'] : 'utf8',
                    'collation' => !empty($db_cfg['collation']) ? $db_cfg['collation'] : 'utf8_general_ci',
                    'prefix'    => $db_cfg['prefix'],
                ], $db_name);
            }
            
            unset($db_name, $db_cfg);
            
            $this->orm->setEventDispatcher(
                new \Illuminate\Events\Dispatcher(
                    new \Illuminate\Container\Container()
                )
            );
            
            $this->orm->setAsGlobal();
            $this->orm->bootEloquent();
        }
        
        class_alias('\\Illuminate\\Database\\Capsule\\Manager', '\\DB');
    }
    
    public function db($connection = 'default'): \Illuminate\Database\MySqlConnection
    {
        return \Illuminate\Database\Capsule\Manager::connection($connection);    
    }
    
    public function table($table, $connection = 'default'): \Illuminate\Database\Query\Builder
    {
        return $this->db($connection)->table($table);    
    }
    
    public function hasTable($tablename, $connection = 'default', $refresh = false): bool
    {
        $prefix   = $this->db($connection)->getTablePrefix();
        $database = $this->db($connection)->getDatabaseName();
        
        if( !is_array($this->tables) ) {
            $this->tables = [];
        }
        
        if( empty($this->tables[$connection]) ) {
            $this->tables[ $connection ] = [];
        }
        
        if( empty($this->tables[$connection]) || $refresh ) {
            $field_name = "Tables_in_{$database}";
            $data       = $this->db($connection)->select( \DB::raw("SHOW TABLES FROM {$database}") );
            
            foreach($data as $item) {
                $this->tables[ $connection ][] = str_replace($prefix, '', $item->$field_name);
            }
        }
        
        if( !empty($this->tables[$connection]) && in_array($tablename, $this->tables[$connection]) ) {
            return true;
        }
        
        return false;
    }
    
    public function getTableFieldsList($tablename, $connection = 'default'): array
    {
        if( !is_array($this->fields) ) {
            $this->fields = [];
        }
        
        if( !array_key_exists($connection, $this->fields) ) {
            $this->fields[ $connection ] = [];
        }
        
        if( !empty($this->fields[$connection][$tablename]) ) {
            return $this->fields[ $connection ][ $tablename ];
        }
        
        $prefix = $this->db($connection)->getTablePrefix();
            
        $_     = [];
        $items = $this->db($connection)->select( \DB::raw("SHOW FIELDS FROM {$prefix}{$tablename}") );
            
        foreach($items as $item) {
            $_[] = $item->Field;
        }
            
        return $this->fields[ $connection ][ $tablename ] = $_;
    }
    
    public function schema($connection = 'default'): \Illuminate\Database\Schema\MySqlBuilder
    {
        return $this->orm->connection($connection)->getSchemaBuilder();
    }
}