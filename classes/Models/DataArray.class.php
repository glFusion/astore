<?php
/**
 * Utility class to handle array data.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2023 Lee Garner <lee@leegarner.com>
 * @package     astore
 * @version     v0.3.0
 * @since       v0.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Astore\Models;
use glFusion\Log\Log;


/**
 * General array handler.
 * @package astore
 */
class DataArray implements \ArrayAccess, \Iterator
{
    protected $position = '';
    protected $properties = array();


    /**
     * Initialize the properties from a supplied string or array.
     * Use array_replace to preserve default properties by child classes.
     *
     * @param   string|array    $val    Optonal initial properties
     */
    public function __construct(?array $A=NULL)
    {
        if (is_array($A)) {
            $this->properties = array_replace($this->properties, $A);
        }
    }


    /**
     * Create an instance from an array.
     * Just a wrapper for `new DataArray($A)`.
     *
     * @param   array   $A      Array of properties
     * @return  object      New DataArray
     */
    public static function fromArray(array $A) : self
    {
        return new self($A);
    }


    /**
     * Create an instance from a JSON-encoded string.
     *
     * @param   string  $str    JSON string to decode into an array
     * @return  object      New DataArray
     */
    public static function fromString(string $str) : self
    {
        try {
            $A = json_decode($str, true);
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ": Error decoding $str");
            $A = array();
        }
        return new self($A);
    }


    /**
     * Set a property when accessing as an array.
     *
     * @param   string  $key    Property name
     * @param   mixed   $value  Property value
     */
    public function offsetSet($key, $value)
    {
        $this->properties[$key] = $value;
    }


    /**
     * Check if a property is set when calling `isset($this)`.
     *
     * @param   string  $key    Property name
     * @return  boolean     True if property exists, False if not
     */
    public function offsetExists($key)
    {
        return isset($this->properties[$key]);
    }


    /**
     * Remove a property when using unset().
     *
     * @param   string  $key    Property name
     */
    public function offsetUnset($key)
    {
        unset($this->properties[$key]);
    }


    /**
     * Get a property when referencing the class as an array.
     *
     * @param   string  $key    Property name
     * @return  mixed       Property value, NULL if not set
     */
    public function offsetGet($key)
    {
        return isset($this->properties[$key]) ? $this->properties[$key] : NULL;
    }


    /**
     * Check if the properties array is empty.
     *
     * @return  boolean     True if empty, False if not
     */
    public function isEmpty() : bool
    {
        return empty($this->properties);
    }


    /**
     * Get the internal properties as a native array.
     *
     * @return  array   $this->properties
     */
    public function toArray() : array
    {
        return $this->properties;
    }


    /**
     * Return the string representation of the class.
     *
     * @return  string      Base64-encoded json string
     */
    public function __toString() : string
    {
        return json_encode($this->properties);
    }


    /**
     * Merge the supplied array into the internal properties.
     *
     * @param   array   $arr    Array of data to add
     * @return  object  $this
     */
    public function merge(array $arr) : self
    {
        $this->properties = array_merge($this->properties, $arr);
        return $this;
    }


    public function getString(string $var, ?string $default='') : ?string
    {
        if (!array_key_exists($var, $this->properties)) {
            return $default;
        } else {
            return (string)$this->properties[$var];
        }
    }


    public function getInt(string $var, ?int $default=0) : ?int
    {
        if (!array_key_exists($var, $this->properties)) {
            return $default;
        } else {
            return (int)$this->properties[$var];
        }
    }


    public function getFloat(string $var, ?float $default=0) : ?float
    {
        if (!array_key_exists($var, $this->properties)) {
            return $default;
        } else {
            return (float)$this->properties[$var];
        }
    }


    /**
     * Get a boolean value.
     *
     * @param   string  $var    Key name
     * @return  array       Array value, empty array if undefined
     */
    public function getBool(string $var, bool $default=false) : bool
    {
        if (!array_key_exists($var, $this->properties)) {
            return $default;
        } elseif ($this->properties[$var] == 'false') {
            // Handle "false" string, which evaluates to boolean true.
            return false;
        } else {
            return (bool)$this->properties[$var];
        }
    }


    /**
     * Get an array value. Converts to an array if necessary.
     *
     * @param   string  $var    Key name
     * @return  array       Array value, empty array if undefined
     */
    public function getArray(string $var, ?array $default=array()) : ?array
    {
        if (!array_key_exists($var, $this->properties)) {
            $retval = $default;
        } else {
            $retval = $this->properties[$var];
        }
        if (!is_array($retval)) {
            if (empty($retval)) {
                $retval = array();
            } else {
                $retval = array($retval);
            }
        }
        return $retval;
    }


    /**
     * Get a raw property value, if the type is unknown.
     *
     * @param   string  $key    Key name
     * @return  mixed       Property value, NULL if undefined
     */
    public function get(string $key, $default=NULL)
    {
        if (array_key_exists($key, $this->properties)) {
            return $this->properties[$key];
        } else {
            return $default;
        }
    }


    /**
     * Encode the internal properties to a Base64-encode JSON string.
     *
     * @return  string      Encoded string containing the properties
     */
    public function encode() : string
    {
        return base64_encode(@json_encode($this->properties));
    }


    /**
     * Sets the internal properties by decoding the supplied string.
     *
     * @param   string  $data   Base64-encoded JSON string
     * @return  array       Properties array
     */
    public function decode(string $data) : array
    {
        $this->properties = @json_decode(base64_decode($data), true);
        if ($this->properties === NULL) {
            $this->properties = array();
        }
        return $this->properties;
    }


    /**
     * Serialize the properties into a string.
     * Ensures that a valid empty string is returned on error.
     *
     * @return  string  Serialized string
     */
    public function serialize() : string
    {
        $retval = @serialize($this->properties);
        if ($retval === false) {
            $retval = '';
        }
        return $retval;
    }


    /**
     * Unserialize a string into the properties array.
     * Ensures that a valid empty array is returned on error.
     *
     * @param   string  $data   Serialized string
     * @return  array       Unserialized data
     */
    public function unserialize(string $data) : array
    {
        $this->properties = @unserialize($data);
        if ($this->properties === false) {
            $this->properties = array();
        }
        return $this->properties;
    }


    /**
     * Implode the properties into a string separated by the given character.
     * Just a wrapper for the native implode() function, included for
     * consistency with self::explode().
     *
     * @param   string  $char   Separation character, e.g ','
     * @return  string      String with properties separated by $char
     */
    public function implode(string $char) : string
    {
        return implode($char, $this->properties);
    }


    /**
     * Explode a property that is a string, e.g. CSV.
     * Unlike regular `explode()`, this returns a truly empty array if
     * the property is not set and does not include an empty element "0".
     *
     * @param   string  $char   Separation character
     * @param   string  $key    Key into the properties array
     * @return  array       Exploded array, empty if $key is not set
     */
    public function explode(string $char, string $key) : array
    {
        if (isset($this->properties[$key]) && !empty($this->properties[$key])) {
            return explode($char, $this->properties[$key]);
        } else {
            return array();
        }
    }

    /**
     * Reset the array.
     * Base function is to empty the array, child classes that have
     * specific array structures should implement their own reset() function.
     */
    public function reset() : void
    {
        $this->properties = array();
    }

    public function rewind() : void
    {
        reset($this->properties);
    }

    #[ReturnTypeWillChange]
    public function current()
    {
        return current($this->properties);
    }

    #[ReturnTypeWillChange]
    public function key()
    {
        return key($this->properties);
    }

    public function next(): void
    {
        next($this->properties);
    }

    public function valid(): bool
    {
        return key($this->properties) !== NULL;
    }

}
