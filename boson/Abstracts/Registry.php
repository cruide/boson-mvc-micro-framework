<?php namespace Boson\Abstracts;
/**
 * Class Registry
 *
 * Базовый абстрактный реестр/контейнер свойств.
 *
 * Назначение:
 * - хранение произвольного набора свойств в массиве `$properties`;
 * - доступ к данным как через объект (`$registry->name`),
 *   так и через массив (`$registry['name']`);
 * - поддержка аксессоров и мутаторов по соглашению:
 *      - get{Name}Attribute()
 *      - set{Name}Attribute($value)
 * - экспорт в массив и JSON;
 * - удобная массовая загрузка данных.
 *
 * Совместимость:
 * - PHP 8.0+
 * - без строгой типизации свойств и аргументов
 *
 * Особенности реализации:
 * - используется `array_key_exists()`, чтобы корректно отличать:
 *      - ключ существует и его значение равно `null`
 *      - ключ не существует
 * - `__isset()` намеренно использует `isset()`, чтобы сохранить
 *   нативное поведение PHP для `isset($obj->prop)`:
 *   если значение `null`, результат будет `false`
 *
 * Пример:
 *
 * ```php
 * $registry = new SomeRegistry();
 * $registry->set('name', 'Alex');
 *
 * echo $registry->name;      // Alex
 * echo $registry['name'];    // Alex
 *
 * print_r($registry->toArray());
 * echo $registry->toJson();
 * ```
 *
 * @package Boson\Abstracts
 * @author  Tishchenko Alexander
 */
abstract class Registry extends \stdClass implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable
{
    use \Boson\Traits\ClassName;

    /**
     * Внутреннее хранилище свойств.
     *
     * Весь набор данных Registry хранится здесь.
     *
     * @var array
     */
    protected $properties = [];

    /**
     * Конструктор.
     *
     * Позволяет сразу заполнить реестр начальными данными.
     *
     * @param array|\Traversable|null $properties
     */
    public function __construct($properties = null)
    {
        if( $properties !== null ) {
            $this->fill($properties);
        }
    }

    /**
     * Магический getter.
     *
     * Перенаправляет доступ к несуществующему публичному свойству
     * на метод `get()`.
     *
     * @param string $name Имя свойства
     * @return mixed|null
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * Магический setter.
     *
     * Перенаправляет присвоение несуществующему публичному свойству
     * на метод `set()`.
     *
     * @param string $name  Имя свойства
     * @param mixed  $value Значение
     * @return void
     */
    public function __set($name, $value)
    {
        $this->set($name, $value);
    }

    /**
     * Магическая проверка свойства через isset().
     *
     * Важно:
     * - если ключ существует, но его значение `null`,
     *   `isset()` вернёт `false`
     *
     * Это соответствует стандартной семантике PHP.
     *
     * @param string $name Имя свойства
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->properties[$name]);
    }

    /**
     * Магическое удаление свойства.
     *
     * @param string $name Имя свойства
     * @return void
     */
    public function __unset($name)
    {
        unset($this->properties[$name]);
    }

    /**
     * Преобразование объекта в строку.
     *
     * Возвращает JSON-представление объекта.
     * В случае ошибки кодирования вернёт пустой JSON-объект.
     *
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->toJson();
            
        } catch (\Throwable $e) {
            return '{}';
        }
    }

    /**
     * Проверяет существование ключа в реестре.
     *
     * В отличие от `isset()`, метод `has()` считает ключ существующим,
     * даже если его значение равно `null`.
     *
     * @param string|int $name Имя ключа
     * @return bool
     */
    public function has($name)
    {
        if( $name === null || $name === '' ) {
            return false;
        }

        return array_key_exists($name, $this->properties);
    }

    /**
     * Возвращает true, если реестр пуст.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->properties);
    }

    /**
     * Устанавливает значение свойства.
     *
     * Алгоритм:
     * 1. Если существует мутатор вида `set{Name}Attribute($value)`,
     *    используется он.
     * 2. Иначе значение записывается напрямую.
     *
     * Пример:
     * - для ключа `first_name`
     *   будет искаться метод `setFirstNameAttribute`
     *
     * @param string|int $name  Имя ключа
     * @param mixed      $value Значение
     * @return $this
     */
    public function set($name, $value = null)
    {
        $mutatorName = 'set' . $this->studlyCase($name) . 'Attribute';

        if( method_exists($this, $mutatorName) ) {
            $this->properties[ $name ] = $this->$mutatorName($value);
            
            return $this;
        }

        $this->properties[ $name ] = $value;

        return $this;
    }

    /**
     * Возвращает значение свойства.
     *
     * Алгоритм:
     * 1. Если существует аксессор вида `get{Name}Attribute()`,
     *    вызывается он.
     * 2. Если ключ существует в `$properties`, возвращается его значение.
     * 3. Иначе возвращается `$default`.
     *
     * @param string|int $name    Имя ключа
     * @param mixed      $default Значение по умолчанию
     * @return mixed
     */
    public function get($name, $default = null)
    {
        $accessorName = 'get' . $this->studlyCase($name) . 'Attribute';

        if( method_exists($this, $accessorName) ) {
            return $this->$accessorName();
        }

        if( array_key_exists($name, $this->properties) ) {
            return $this->properties[ $name ];
        }

        return $default;
    }

    /**
     * Возвращает значение свойства и удаляет его из реестра.
     *
     * Полезно для "одноразового" чтения параметра.
     *
     * @param string|int $name
     * @param mixed      $default
     * @return mixed
     */
    public function pull($name, $default = null)
    {
        $value = $this->get($name, $default);
        
        $this->remove($name);

        return $value;
    }

    /**
     * Удаляет свойство по имени.
     *
     * @param string|int $name
     * @return $this
     */
    public function remove($name)
    {
        unset($this->properties[$name]);

        return $this;
    }

    /**
     * Полностью очищает реестр.
     *
     * @return $this
     */
    public function clear()
    {
        $this->properties = [];

        return $this;
    }

    /**
     * Возвращает все данные в виде массива.
     *
     * Это алиас к `toArray()`.
     *
     * @return array
     */
    public function all()
    {
        return $this->toArray();
    }

    /**
     * Заменяет все текущие данные новыми.
     *
     * Сначала очищает реестр, затем вызывает `fill()`.
     *
     * @param array|\Traversable $properties
     * @return $this
     */
    public function replace($properties)
    {
        $this->clear();

        return $this->fill($properties);
    }

    /**
     * Объединяет текущие данные с новыми.
     *
     * По сути эквивалентен `fill()`, но семантически подчёркивает merge.
     *
     * @param array|\Traversable $properties
     * @return $this
     */
    public function merge($properties)
    {
        return $this->fill($properties);
    }

    /**
     * Возвращает только указанные ключи.
     *
     * @param array $keys
     * @return array
     */
    public function only($keys)
    {
        $result = [];

        foreach ((array) $keys as $key) {
            if ($this->has($key)) {
                $result[$key] = $this->get($key);
            }
        }

        return $result;
    }

    /**
     * Возвращает все ключи, кроме указанных.
     *
     * @param array $keys
     * @return array
     */
    public function except($keys)
    {
        $exclude = array_flip((array)$keys);
        $result  = [];

        foreach($this->properties as $key=>$value) {
            if( !isset($exclude[$key]) ) {
                $result[ $key ] = $this->get($key);
            }
        }

        return $result;
    }

    /**
     * Подсчитывает количество элементов.
     *
     * Реализация интерфейса `Countable`.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->properties);
    }

    /**
     * Сериализует реестр в строку PHP serialize().
     *
     * В сериализацию попадает экспорт через `toArray()`, а не сырые данные.
     * Это позволяет учитывать аксессоры и вложенные объекты.
     *
     * @return string
     */
    public function serialize()
    {
        return serialize($this->toArray());
    }

    /**
     * Возвращает JSON-представление реестра.
     *
     * Если в классе-потомке существует метод `jsonTransform($data)`,
     * то перед кодированием в JSON данные будут пропущены через него.
     *
     * @param int $options Опции json_encode()
     * @return string
     */
    public function toJson($options = 0)
    {
        $data = $this->toArray();

        if( method_exists($this, 'jsonTransform') ) {
            $data = $this->jsonTransform($data);
        }

        $json = json_encode($data, $options | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return ($json === false) ? '{}' : $json;
    }

    /**
     * Преобразует реестр в массив.
     *
     * Особенности:
     * - значения читаются через `get()`, чтобы учитывались аксессоры;
     * - вложенные объекты с методом `toArray()` преобразуются рекурсивно;
     * - массивы также обрабатываются рекурсивно.
     *
     * @return array
     */
    public function toArray(): array
    {
        $result = [];

        foreach($this->properties as $key=>$value) {
            $result[ $key ] = $this->normalizeValueForArray( $this->get($key) );
        }

        return $result;
    }

    /**
     * Массово заполняет реестр данными.
     *
     * Допустимые источники:
     * - массив
     * - объект Traversable
     *
     * Все значения проходят через `set()`, чтобы не обходить мутаторы.
     *
     * Ключи валидируются как имена переменных PHP, если они строковые.
     * Числовые ключи также допускаются.
     *
     * @param array|\Traversable $properties
     * @return $this
     */
    public function fill($properties): self
    {
        if( $properties instanceof \Traversable ) {
            $properties = iterator_to_array($properties);
        }

        if( !is_array($properties) || empty($properties) ) {
            return $this;
        }

        foreach($properties as $key=>$value) {
            if( $this->isAllowedKey($key) ) {
                $this->set($key, $value);
            }
        }

        return $this;
    }

    /**
     * Проверяет существование элемента по offset.
     *
     * Реализация интерфейса `ArrayAccess`.
     *
     * @param mixed $key
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * Возвращает элемент по offset.
     *
     * Реализация интерфейса `ArrayAccess`.
     *
     * @param mixed $key
     * @return mixed|null
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * Устанавливает элемент по offset.
     *
     * Если ключ равен `null`, элемент будет добавлен как в обычный массив.
     *
     * @param mixed $key
     * @param mixed $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if( $offset === null ) {
            $this->properties[] = $value;
            
            return;
        }

        $this->set($offset, $value);
    }

    /**
     * Удаляет элемент по offset.
     *
     * @param mixed $key
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->properties[$offset]);
    }

    /**
     * Возвращает итератор для foreach.
     *
     * Реализация интерфейса `IteratorAggregate`.
     *
     * Итерация идёт по "экспортированному" массиву, а не по сырым данным,
     * чтобы учитывать аксессоры и преобразования.
     *
     * @return \ArrayIterator
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->toArray());
    }

    /**
     * Реализация интерфейса `JsonSerializable`.
     *
     * Возвращает массив данных, который затем будет сериализован
     * функцией `json_encode()`.
     *
     * Важно:
     * - здесь возвращается массив, а не строка JSON
     * - это корректное поведение для JsonSerializable
     *
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        $data = $this->toArray();

        if( method_exists($this, 'jsonTransform') ) {
            $data = $this->jsonTransform($data);
        }

        return $data;
    }

    /**
     * Преобразует имя поля в StudlyCase.
     *
     * Примеры:
     * - first_name => FirstName
     * - first-name => FirstName
     * - first name => FirstName
     *
     * Локальная реализация нужна для независимости от внешних helper-функций.
     *
     * @param string $value
     * @return string
     */
    protected function studlyCase($value)
    {
        $value = (string) $value;
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);
        $value = str_replace(' ', '', $value);

        return $value;
    }

    /**
     * Проверяет допустимость ключа для записи в реестр.
     *
     * Допускаются:
     * - целые числа
     * - строки, похожие на корректные имена переменных PHP
     *
     * @param mixed $key
     * @return bool
     */
    protected function isAllowedKey($key)
    {
        if( is_int($key) ) {
            return true;
        }

        if( !is_string($key) || $key === '' ) {
            return false;
        }

        return (bool)preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $key);
    }

    /**
     * Нормализует значение для экспорта в массив.
     *
     * Правила:
     * - если объект имеет `toArray()` — вызывается он;
     * - если объект реализует `JsonSerializable` — берётся `jsonSerialize()`;
     * - если это массив — обрабатывается рекурсивно;
     * - иначе возвращается как есть.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function normalizeValueForArray($value)
    {
        if( is_array($value) ) {
            $result = [];

            foreach($value as $key=>$item) {
                $result[ $key ] = $this->normalizeValueForArray($item);
            }

            return $result;
        }

        if( is_object($value) ) {
            if( method_exists($value, 'toArray') ) {
                return $value->toArray();
            }

            if( $value instanceof \JsonSerializable ) {
                return $value->jsonSerialize();
            }
        }

        return $value;
    }
}
