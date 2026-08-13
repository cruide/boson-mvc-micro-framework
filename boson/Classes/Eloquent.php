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
    protected $connections = [];
    
    public function __construct()
    {
        $this->cfg = cfg('database');
        $this->orm = new \Illuminate\Database\Capsule\Manager();
        
        foreach($this->parseConnections() as $db_name => $db_cfg) {
            $this->orm->addConnection($db_cfg, $db_name);
            $this->connections[] = $db_name;
        }
        
        if( !empty($this->connections) ) {
            $this->orm->setEventDispatcher(
                new \Illuminate\Events\Dispatcher(
                    new \Illuminate\Container\Container()
                )
            );
            
            $this->orm->setAsGlobal();
            $this->orm->bootEloquent();
        }
        
        if( !class_exists('\\DB', false) ) {
            class_alias('\\Illuminate\\Database\\Capsule\\Manager', '\\DB');
        }
    }
    
    /**
     * Разбирает конфигурацию БД (database.ini) в массив соединений.
     *
     * Каждая секция ini-файла — отдельное соединение. Секция `[default]`
     * используется как соединение по умолчанию.
     *
     * @return array Массив вида [имя_соединения => конфиг соединения]
     */
    protected function parseConnections(): array
    {
        if( $this->cfg === null ) {
            return [];
        }
        
        if( is_object($this->cfg) && method_exists($this->cfg, 'toArray') ) {
            $config = $this->cfg->toArray();
        } elseif( is_array($this->cfg) ) {
            $config = $this->cfg;
        } else {
            return [];
        }
        
        $connections = [];
        
        foreach($config as $db_name => $db_cfg) {
            if( !is_array($db_cfg) || empty($db_cfg['database']) ) {
                continue;
            }
            
            $connections[$db_name] = [
                'driver'    => !empty($db_cfg['driver']) ? $db_cfg['driver'] : 'mysql',
                'host'      => !empty($db_cfg['host']) ? $db_cfg['host'] : 'localhost',
                'port'      => !empty($db_cfg['port']) ? $db_cfg['port'] : 3306,
                'database'  => $db_cfg['database'],
                'username'  => !empty($db_cfg['username']) ? $db_cfg['username'] : '',
                'password'  => !empty($db_cfg['password']) ? $db_cfg['password'] : '',
                'charset'   => !empty($db_cfg['charset']) ? $db_cfg['charset'] : 'utf8',
                'collation' => !empty($db_cfg['collation']) ? $db_cfg['collation'] : 'utf8_general_ci',
                'prefix'    => !empty($db_cfg['prefix']) ? $db_cfg['prefix'] : '',
            ];
        }
        
        return $connections;
    }
    
    /**
     * Определяет имя соединения для запроса.
     *
     * Если соединение не передано явно, возвращает `default` (при его наличии)
     * либо первое зарегистрированное соединение.
     *
     * @param string|null $connection Имя соединения или null
     * @return string
     */
    protected function resolveConnection($connection = null): string
    {
        if( !empty($connection) ) {
            return $connection;
        }
        
        if( in_array('default', $this->connections, true) ) {
            return 'default';
        }
        
        if( !empty($this->connections) ) {
            return $this->connections[0];
        }
        
        return 'default';
    }
    
    /**
     * Экранирует имя таблицы/БД обратными кавычками.
     */
    protected function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
    
    public function db($connection = null): \Illuminate\Database\Connection
    {
        return \Illuminate\Database\Capsule\Manager::connection( $this->resolveConnection($connection) );
    }
    
    public function table($table, $connection = null): \Illuminate\Database\Query\Builder
    {
        return $this->db($connection)->table($table);
    }
    
    public function hasTable($tablename, $connection = null, $refresh = false): bool
    {
        $connection = $this->resolveConnection($connection);
        $db         = $this->db($connection);
        $prefix     = $db->getTablePrefix();
        $database   = $db->getDatabaseName();
        
        if( !is_array($this->tables) ) {
            $this->tables = [];
        }
        
        if( $refresh || !array_key_exists($connection, $this->tables) ) {
            $this->tables[ $connection ] = [];
            
            $field_name = "Tables_in_{$database}";
            $data       = $db->select( "SHOW TABLES FROM " . $this->quoteIdentifier($database) );
            
            foreach($data as $item) {
                $name = $item->$field_name;
                
                if( $prefix !== '' && str_starts_with($name, $prefix) ) {
                    $name = substr($name, strlen($prefix));
                }
                
                $this->tables[ $connection ][] = $name;
            }
        }
        
        return in_array($tablename, $this->tables[ $connection ], true);
    }
    
    public function getTableFieldsList($tablename, $connection = null): array
    {
        $connection = $this->resolveConnection($connection);
        $db         = $this->db($connection);
        
        if( !is_array($this->fields) ) {
            $this->fields = [];
        }
        
        if( !array_key_exists($connection, $this->fields) ) {
            $this->fields[ $connection ] = [];
        }
        
        if( array_key_exists($tablename, $this->fields[ $connection ]) ) {
            return $this->fields[ $connection ][ $tablename ];
        }
        
        $prefix = $db->getTablePrefix();
        
        $_     = [];
        $items = $db->select( "SHOW FIELDS FROM " . $this->quoteIdentifier($prefix . $tablename) );
        
        foreach($items as $item) {
            $_[] = $item->Field;
        }
        
        return $this->fields[ $connection ][ $tablename ] = $_;
    }
    
    public function schema($connection = null): \Illuminate\Database\Schema\Builder
    {
        return $this->db($connection)->getSchemaBuilder();
    }
}
