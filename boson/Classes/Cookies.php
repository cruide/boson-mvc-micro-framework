<?php namespace Boson;
/**
* @name      Boson PHP framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @copyright Copyright (c) 2018 All rights reserved
* @version   2.1
*
* Работа с cookies. Поддерживает магический доступ,
* настраиваемое время жизни, безопасные параметры по умолчанию.
*/

use Boson\Traits\SingletonTrait;

class Cookies
{
    use SingletonTrait;

    protected $_properties = [];

    /** @var int Время жизни cookie по умолчанию (в минутах) */
    protected $_defaultExpire = 10080; // 7 дней

    public function __construct()
    {
        if( !empty($_COOKIE) ) {
            foreach($_COOKIE as $key => $val) {
                if( $key !== 'PHPSESSID' ) {
                    $this->_properties[ $key ] = $val;
                }
            }
        }
    }

    /**
     * Установить время жизни cookie по умолчанию (в минутах).
     */
    public function setDefaultExpire(int $minutes): self
    {
        $this->_defaultExpire = $minutes;
        return $this;
    }

    /**
     * Получить значение cookie.
     */
    public function get($name, $default = null)
    {
        return $this->_properties[ $name ] ?? $default;
    }

    /**
     * Установить cookie.
     *
     * @param string $name    Имя
     * @param mixed  $value   Значение (null — удалить)
     * @param int    $minutes Время жизни в минутах (0 = до закрытия браузера)
     */
    public function set($name, $value, $minutes = null): self
    {
        if( $value === null ) {
            return $this->forget($name);
        }

        if( $minutes === null ) {
            $minutes = $this->_defaultExpire;
        }

        $expires = $minutes > 0 ? time() + ($minutes * 60) : 0;

        setcookie($name, (string)$value, [
            'expires'  => $expires,
            'path'     => '/',
            'httponly' => true,
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Strict',
        ]);

        $this->_properties[ $name ] = $value;

        return $this;
    }

    /**
     * Удалить cookie.
     */
    public function forget($name): self
    {
        setcookie($name, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Strict',
        ]);

        unset($this->_properties[ $name ]);

        return $this;
    }

    /**
     * Проверить существование cookie.
     */
    public function has($name): bool
    {
        return array_key_exists($name, $this->_properties);
    }

    /**
     * Вернуть все cookie (кроме PHPSESSID).
     */
    public function all(): array
    {
        return $this->_properties;
    }

    /**
    * Cookie getter for $this->_properties
    *
    * @param string $name
    */
    public function __get($name)
    {
        return $this->_properties[ $name ] ?? null;
    }

    /**
    * Cookie setter for $this->_properties
    *
    * @param string $name
    * @param mixed $value
    */
    public function __set($name, $value)
    {
        return $this->set($name, $value);
    }

    /**
    * Cookie isset for $this->_properties
    *
    * @param string $name
    * @return bool
    */
    public function __isset($name)
    {
        return isset($this->_properties[ $name ]);
    }

    /**
    * Cookie unset for $this->_properties
    *
    * @param string $name
    */
    public function __unset($name)
    {
        $this->forget($name);
    }
}
