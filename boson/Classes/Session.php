<?php namespace Boson;
/**
* @name      Boson PHP micro framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @link      http://alex-tisch.ru
* @copyright Copyright (c) 2018 All rights reserved
* @version   2.1
*
* Working with sessions. Supports magic property access,
* flash messages, ID regeneration.
*/

use Boson\Traits\SingletonTrait;

class Session
{
    use SingletonTrait;
    
    protected $_session_id;

    public function __construct()
    {
        if( !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ) {
            ini_set('session.cookie_secure', '1');
        }
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');

        session_start();
        $this->_session_id = session_id();
    }

    public function id( $crypt = false )
    {
        return $crypt ? md5($this->_session_id) : $this->_session_id;
    }

    public function destroy()
    {
        session_destroy();
    }

    public function regenerate()
    {
        session_regenerate_id(true);
        $this->_session_id = session_id();
    }

    /**
     * Get a value from the session.
     */
    public function get($key, $default = null)
    {
        return $_SESSION[ $key ] ?? $default;
    }

    /**
     * Write a value to the session.
     */
    public function set($key, $value): self
    {
        $_SESSION[ $key ] = $value;
        return $this;
    }

    /**
     * Check whether a key exists (including with a null value).
     */
    public function has($key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    /**
     * Remove a key from the session.
     */
    public function remove($key): self
    {
        unset($_SESSION[ $key ]);
        return $this;
    }

    /**
     * Flash message: the value is only available on the next request, then it is removed.
     *
     * @param string $key   Key
     * @param mixed  $value If null — delete
     * @return mixed|null   Current value (when reading)
     */
    public function flash($key, $value = null)
    {
        $flashKey = '_flash_' . $key;

        if( $value === null ) {
            $val = $_SESSION[ $flashKey ] ?? null;

            // If this is not yet a removed flash (first read in this request)
            if( ($_SESSION[ '_flash_consumed_' . $key ] ?? null) !== true ) {
                unset($_SESSION[ $flashKey ]);
                $_SESSION[ '_flash_consumed_' . $key ] = true;
            }

            return $val;
        }

        $_SESSION[ $flashKey ] = $value;
        unset($_SESSION[ '_flash_consumed_' . $key ]);

        return $this;
    }

    /**
     * Return all session data.
     */
    public function all(): array
    {
        $result = [];

        foreach($_SESSION as $key => $val) {
            if( str_starts_with((string)$key, '_flash_') ) {
                continue;
            }
            $result[ $key ] = $val;
        }

        return $result;
    }

    /**
     * Completely clear the session.
     */
    public function clear(): self
    {
        $_SESSION = [];
        return $this;
    }
    
    /**
    * Cookie getter for $_SESSION
    *
    * @param string $name
    */
    public function __get($name)
    {
        return $_SESSION[ $name ] ?? null;
    }

    /**
    * Cookie setter for $_SESSION
    *
    * @param string $name
    * @param mixed $value
    */
    public function __set($name, $value)
    {
        $_SESSION[ $name ] = $value;
    }

    /**
    * Cookie isset for $_SESSION
    *
    * @param string $name
    * @return bool
    */
    public function __isset($name)
    {
        return isset($_SESSION[ $name ]);
    }

    /**
    * Cookie unset for $_SESSION
    *
    * @param string $name
    */
    public function __unset($name)
    {
        unset($_SESSION[ $name ]);
    }
}
