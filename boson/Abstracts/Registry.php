<?php namespace Boson\Abstracts;
/**
 * Class Registry
 *
 * Base abstract registry / property container.
 *
 * Purpose:
 * - stores an arbitrary set of properties in the `$properties` array;
 * - provides access to data both as an object (`$registry->name`)
 *   and as an array (`$registry['name']`);
 * - supports accessors and mutators by convention:
 *      - get{Name}Attribute()
 *      - set{Name}Attribute($value)
 * - exports to an array and JSON;
 * - convenient bulk data loading.
 *
 * Compatibility:
 * - PHP 8.0+
 * - no strict typing of properties and arguments
 *
 * Implementation details:
 * - uses `array_key_exists()` to correctly distinguish between:
 *      - the key exists and its value is `null`
 *      - the key does not exist
 * - `__isset()` intentionally uses `isset()` to preserve
 *   the native PHP behavior for `isset($obj->prop)`:
 *   if the value is `null`, the result is `false`
 *
 * Example:
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
     * Internal property storage.
     *
     * The entire Registry dataset is stored here.
     *
     * @var array
     */
    protected $properties = [];

    /**
     * Constructor.
     *
     * Allows populating the registry with initial data right away.
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
     * Magic getter.
     *
     * Redirects access to a non-existent public property
     * to the `get()` method.
     *
     * @param string $name Property name
     * @return mixed|null
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * Magic setter.
     *
     * Redirects assignment to a non-existent public property
     * to the `set()` method.
     *
     * @param string $name  Property name
     * @param mixed  $value Value
     * @return void
     */
    public function __set($name, $value)
    {
        $this->set($name, $value);
    }

    /**
     * Magic property check via isset().
     *
     * Important:
     * - if the key exists but its value is `null`,
     *   `isset()` returns `false`
     *
     * This matches the standard PHP semantics.
     *
     * @param string $name Property name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->properties[$name]);
    }

    /**
     * Magic property deletion.
     *
     * @param string $name Property name
     * @return void
     */
    public function __unset($name)
    {
        unset($this->properties[$name]);
    }

    /**
     * Convert the object to a string.
     *
     * Returns the JSON representation of the object.
     * Returns an empty JSON object if encoding fails.
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
     * Checks whether a key exists in the registry.
     *
     * Unlike `isset()`, the `has()` method considers a key to exist
     * even if its value is `null`.
     *
     * @param string|int $name Key name
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
     * Returns true if the registry is empty.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->properties);
    }

    /**
     * Sets a property value.
     *
     * Algorithm:
     * 1. If a mutator of the form `set{Name}Attribute($value)` exists,
     *    it is used.
     * 2. Otherwise the value is stored directly.
     *
     * Example:
     * - for the `first_name` key
     *   the `setFirstNameAttribute` method will be looked up
     *
     * @param string|int $name  Key name
     * @param mixed      $value Value
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
     * Returns a property value.
     *
     * Algorithm:
     * 1. If an accessor of the form `get{Name}Attribute()` exists,
     *    it is called.
     * 2. If the key exists in `$properties`, its value is returned.
     * 3. Otherwise `$default` is returned.
     *
     * @param string|int $name    Key name
     * @param mixed      $default Default value
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
     * Returns a property value and removes it from the registry.
     *
     * Useful for "one-shot" reads of a parameter.
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
     * Removes a property by name.
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
     * Completely clears the registry.
     *
     * @return $this
     */
    public function clear()
    {
        $this->properties = [];

        return $this;
    }

    /**
     * Returns all data as an array.
     *
     * This is an alias of `toArray()`.
     *
     * @return array
     */
    public function all()
    {
        return $this->toArray();
    }

    /**
     * Replaces all current data with new data.
     *
     * First clears the registry, then calls `fill()`.
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
     * Merges the current data with new data.
     *
     * Essentially equivalent to `fill()`, but semantically emphasizes merging.
     *
     * @param array|\Traversable $properties
     * @return $this
     */
    public function merge($properties)
    {
        return $this->fill($properties);
    }

    /**
     * Returns only the specified keys.
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
     * Returns all keys except the specified ones.
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
     * Counts the number of elements.
     *
     * Implementation of the `Countable` interface.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->properties);
    }

    /**
     * Serializes the registry into a PHP serialize() string.
     *
     * The serialization uses the export via `toArray()`, not the raw data.
     * This lets accessors and nested objects be taken into account.
     *
     * @return string
     */
    public function serialize()
    {
        return serialize($this->toArray());
    }

    /**
     * Returns the JSON representation of the registry.
     *
     * If the descendant class has a `jsonTransform($data)` method,
     * the data will be passed through it before JSON encoding.
     *
     * @param int $options json_encode() options
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
     * Converts the registry to an array.
     *
     * Details:
     * - values are read via `get()` so that accessors are taken into account;
     * - nested objects with a `toArray()` method are converted recursively;
     * - arrays are also processed recursively.
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
     * Bulk-fills the registry with data.
     *
     * Allowed sources:
     * - an array
     * - a Traversable object
     *
     * All values go through `set()` so that mutators are not bypassed.
     *
     * String keys are validated as PHP variable names.
     * Numeric keys are also allowed.
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
     * Checks whether an element exists at the given offset.
     *
     * Implementation of the `ArrayAccess` interface.
     *
     * @param mixed $key
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * Returns the element at the given offset.
     *
     * Implementation of the `ArrayAccess` interface.
     *
     * @param mixed $key
     * @return mixed|null
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * Sets the element at the given offset.
     *
     * If the key is `null`, the element is appended like in a plain array.
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
     * Removes the element at the given offset.
     *
     * @param mixed $key
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->properties[$offset]);
    }

    /**
     * Returns an iterator for foreach.
     *
     * Implementation of the `IteratorAggregate` interface.
     *
     * Iteration runs over the "exported" array, not the raw data,
     * so that accessors and transformations are taken into account.
     *
     * @return \ArrayIterator
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->toArray());
    }

    /**
     * Implementation of the `JsonSerializable` interface.
     *
     * Returns the data array that will then be serialized
     * by the `json_encode()` function.
     *
     * Important:
     * - an array is returned here, not a JSON string
     * - this is the correct behavior for JsonSerializable
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
     * Converts a field name to StudlyCase.
     *
     * Examples:
     * - first_name => FirstName
     * - first-name => FirstName
     * - first name => FirstName
     *
     * A local implementation is used to avoid depending on external helper functions.
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
     * Checks whether a key is allowed to be written to the registry.
     *
     * Allowed:
     * - integers
     * - strings that look like valid PHP variable names
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
     * Normalizes a value for array export.
     *
     * Rules:
     * - if the object has `toArray()`, it is called;
     * - if the object implements `JsonSerializable`, `jsonSerialize()` is used;
     * - if it is an array, it is processed recursively;
     * - otherwise it is returned as is.
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
