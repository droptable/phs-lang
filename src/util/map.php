<?php

namespace phs\util;

use \ArrayAccess;
use \ArrayIterator;
use \IteratorAggregate;
use \Countable;

class Map implements ArrayAccess, IteratorAggregate, Countable
{
  // memory (array)
  private $mem = [];
  
  /**
   * constructor
   */
  public function __construct()
  {
    // empty
  }
  
  /**
   * add something to the map.
   * should be overriden for typechecks
   * 
   * @param string $key
   * @param mixed $val
   * @return boolean
   */
  public function add($key, $val)
  {
    if (!isset ($this->mem[$key])) {
      $this->mem[$key] = $val;
      return true;
    }
    
    return false;
  }
  
  /**
   * add or override something.
   * should be overriden for typechecks
   * 
   * @param string $key
   * @param mixed $val
   */
  public function set($key, $val)
  {
    $this->mem[$key] = $val;
  }
  
  /**
   * check is a item associated with the given key exists
   * 
   * @param  string  $key
   * @return boolean
   */
  public function has($key)
  {
    return isset ($this->mem[$key]);
  }
  
  /**
   * returns an item for the given key
   * 
   * @param  string $key
   * @return mixed
   */
  public function get($key)
  {
    if (isset ($this->mem[$key]))
      return $this->mem[$key];
    
    return null;
  }
  
  /**
   * deletes an item associated with the given key
   * 
   * @param  string $key
   * @return boolean  true if the the item existed, false otherwise
   */
  public function delete($key)
  {
    if (isset ($this->mem[$key])) {
      unset ($this->mem[$key]);
      return true;
    }
    
    return false;
  }
  
  /* ------------------------------------ */
  /* magic */
  
  public function __set($key, $val)
  {
    // forward to $this->set() for typechecks in derived classes
    $this->set($key, $val);
  }
  
  public function __get($key)
  {
    // forward to $this->get() for typechecks in derived classes
    return $this->get($key);
  }
  
  public function __isset($key)
  {
    // forward to $this->has() for typechecks in derived classes
    return $this->has($key);
  }
  
  public function __unset($key)
  {
    // forward to $this->delete() for typechecks in derived classes
    return $this->delete($key);
  }
  
  /* ------------------------------------ */
  /* ArrayAccess */
  
  public function offsetSet($key, $val)
  {
    // forward to $this->set() for typechecks in derived classes
    $this->set($key, $val);
  }
  
  public function offsetGet($key)
  {
    // forward to $this->get() for typechecks in derived classes
    return $this->get($key);
  }
  
  public function offsetExists($key)
  {
    // forward to $this->has() for typechecks in deireved classes
    return $this->has($key);
  }
  
  public function offsetUnset($key)
  {
    // forward to $this->delete() for typechecks in deirved classes
    return $this->delete($key);
  }
  
  /* ------------------------------------ */
  /* IteratorAggregate */
  
  public function getIterator()
  {
    return new ArrayIterator($this->mem);
  }
  
  /* ------------------------------------ */
  /* Countable */
  
  public function count()
  {
    return count($this->mem);
  }
  
  /* ------------------------------------ */
  
  public static function dump(Map $map, callable $wrp = null)
  {
    if ($wrp === null)
      $wrp = function ($item) { return $item; };
    
    foreach ($map as $item)
      echo $wrp($item), "\n";
  }
}
