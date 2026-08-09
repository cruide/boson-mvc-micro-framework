<?php namespace Boson;
/**
* @name      Boson PHP micro framework
* @author    Tishchenko Alexander (info@alex-tisch.ru)
* @link      http://alex-tisch.ru
* @copyright Copyright (c) 2018 All rights reserved
* @version   2.1
*
* Работа с сессиями. Поддерживает магический доступ к свойствам,
* flash-сообщения, регенерацию ID.
*/

use Boson\Traits\SingletonTrait;

class Session
{
    use SingletonTrait;
    
    protected $_session_id;

    public function __construct()
    {
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
     * Получить значение из сессии.
     */
    public function get($key, $default = null)
    {
        return $_SESSION[ $key ] ?? $default;
    }

    /**
     * Записать значение в сессию.
     */
    public function set($key, $value): self
    {
        $_SESSION[ $key ] = $value;
        return $this;
    }

    /**
     * Проверить существование ключа (в том числе со значением null).
     */
    public function has($key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    /**
     * Удалить ключ из сессии.
     */
    public function remove($key): self
    {
        unset($_SESSION[ $key ]);
        return $this;
    }

    /**
     * Flash-сообщение: значение доступно только при следующем запросе, затем удаляется.
     *
     * @param string $key   Ключ
     * @param mixed  $value Если null — удалить
     * @return mixed|null   Текущее значение (при чтении)
     */
    public function flash($key, $value = null)
    {
        $flashKey = '_flash_' . $key;

        if( $value === null ) {
            $val = $_SESSION[ $flashKey ] ?? null;

            // Если это ещё не удалённый флеш (первое чтение в этом запросе)
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
     * Вернуть все данные сессии.
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
     * Полностью очистить сессию.
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
