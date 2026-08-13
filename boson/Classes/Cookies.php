<?php namespace Boson;
/**
* @name      Boson PHP framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @copyright Copyright (c) 2018 All rights reserved
* @version   2.1
*
* Working with cookies. Supports magic access,
* configurable lifetime, secure defaults.
*/

use Boson\Traits\SingletonTrait;

class Cookies
{
    use SingletonTrait;

    protected $_properties = [];

    /** @var int Default cookie lifetime (in minutes) */
    protected $_defaultExpire = 10080; // 7 days

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
     * Set the default cookie lifetime (in minutes).
     */
    public function setDefaultExpire(int $minutes): self
    {
        $this->_defaultExpire = $minutes;
        return $this;
    }

    /**
     * Get a cookie value.
     */
    public function get($name, $default = null)
    {
        return $this->_properties[ $name ] ?? $default;
    }

    /**
     * Set a cookie.
     *
     * @param string $name    Name
     * @param mixed  $value   Value (null — delete)
     * @param int    $minutes Lifetime in minutes (0 = until browser close)
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
            'secure'   => $this->isSecure(),
            'samesite' => 'Strict',
        ]);

        $this->_properties[ $name ] = $value;

        return $this;
    }

    /**
     * Delete a cookie.
     */
    public function forget($name): self
    {
        setcookie($name, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'secure'   => $this->isSecure(),
            'samesite' => 'Strict',
        ]);

        unset($this->_properties[ $name ]);

        return $this;
    }

    /**
     * Check whether a cookie exists.
     */
    public function has($name): bool
    {
        return array_key_exists($name, $this->_properties);
    }

    /**
     * Return all cookies (except PHPSESSID).
     */
    public function all(): array
    {
        return $this->_properties;
    }

    /**
     * Checks HTTPS (including behind a reverse proxy via X-Forwarded-Proto).
     */
    protected function isSecure(): bool
    {
        if( !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ) {
            return true;
        }

        if( ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null) === 'https' ) {
            return true;
        }

        return false;
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
