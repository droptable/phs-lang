<?php

namespace phs\util;

use \ArrayAccess;
use \ArrayIterator;
use \IteratorAggregate;
use \InvalidArgumentException as IAEx;

/**
 * helper function
 *
 * @param  Dict   $dict
 * @return array
 */
function get_dict_vars(Dict $dict) {
  return get_object_vars($dict);
}

/** dict class: simple key/value store. */
class Dict implements 
  ArrayAccess, IteratorAggregate
{
  /**
   * constructor
   *
   */
  public function __construct()
  {
    // empty
  }
  
  /**
   * __clone
   *
   * @return void
   */
  public function __clone()
  {
    foreach (get_dict_vars($this) as $key => &$val)
      $this->$key = clone $val;
  }
  
  /**
   * returns all properties of this dict as array
   * 
   * @return array 
   */
  public function to_array()
  {
    // using get_object_vars() directly would expose 
    // private/protected properties too.
    return get_dict_vars($this);
  }
  
  /**
   * IteratorAggregate#getIterator()
   *
   * @return ArrayIterator
   */
  public function getIterator()
  {
    // using to_array() saves us a DictIterator abstraction
    return new ArrayIterator($this->to_array());
  }
  
  /**
   * magic get
   *
   * @param  mixed  $key
   * @return mixed
   */
  public function __get($key)
  {
    // not yet defined
    return null;
  }
  
  /**
   * ArrayAccess#offsetGet()
   *
   * @param  mixed $key
   * @return mixed
   */
  public function offsetGet($key)
  {
    // forward to managed get-method
    return $this->get($key);
  }
  
  /**
   * ArrayAccess#offsetSet()
   *
   * @param  mixed $key
   * @param  mixed $val
   */
  public function offsetSet($key, $val)
  {
    // forward to managed set-method
    $this->set($key, $val);
  }
  
  /**
   * ArrayAccess#offsetExists()
   *
   * @param  mixed $key
   * @return boolean
   */
  public function offsetExists($key)
  {
    // forward to managed has-method  
    return $this->has($key);
  }
  
  /**
   * ArrayAccess#offsetUnset()
   *
   * @param  mixed $key
   */
  public function offsetUnset($key)
  {
    // forward to managed delete-method
    $this->delete($key);
  }
  
  /**
   * returns a value of the given key
   *
   * @param  string $key
   * @return mixed
   */
  public function get($key) 
  {
    $key = $this->cast($key);
    return isset ($this->{$key}) ? $this->{$key} : null;
  }
  
  /**
   * sets a key/value pair
   *
   * @param string $key
   * @param mixed $val
   */
  public function set($key, $val)
  {
    $key = $this->cast($key);
    $this->{$key} = $val;
  }
  
  /**
   * checks if a key is set (and not null)
   *
   * @param  string  $key
   * @return boolean
   */
  public function has($key)
  {
    $key = $this->cast($key);
    return isset ($this->{$key});
  }
  
  /**
   * changes a key using a new value.
   *
   * @param  string $key
   * @param  mixed $val
   * @return mixed      the old value
   */
  public function swap($key, $val)
  {
    $key = $this->cast($key);
    $prv = null;
    
    if (isset ($this->{$key}))
      $prv = $this->{$key};
    
    $this->{$key} = $val;
    return $prv;
  }
  
  /**
   * deletes a key/value pair
   *
   * @param  string $key
   * @return boolean
   */
  public function delete($key)
  {
    $key = $this->cast($key);
    if (!isset ($this->{$key}))
      return false;
    
    unset ($this->{$key});
    return true;    
  }
  
  /**
   * casts a dict-key to string
   *
   * @param  mixed $key
   * @return string
   * @throws InvalidArgumentException
   */
  private function cast($key)
  {
    if (is_string($key))
      return $key;
    
    if ((is_int($key) || is_null($key) || is_bool($key) || is_real($key)) || 
        (is_object($key) && method_exists($key, '__toString')))
      return (string) $key;
              
    throw new IAEx('invalid dict-key (' . gettype($key) . ')');
  }
}
