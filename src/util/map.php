<?php

namespace phs\util;

use \ArrayAccess;
use \ArrayIterator;
use \IteratorAggregate;
use \Countable;

use \InvalidArgumentException as IAEx;

/** map-entry used in Map#put() */
interface Entry
{
  /**
   * should return a string (entry-id) used as key in a map.
   * 
   * @return string
   */
  public function key();
}

/** cell: usefull for 'incomplete' or 'placeholder' entries */
final class Cell implements Entry
{  
  // @var string
  private $key;
  
  // @var Entry
  private $entry;
  
  /**
   * constructor
   * 
   * @param Entry $entry
   */
  public function __construct(Entry $entry)
  {
    $this->key = $entry->key();
    $this->entry = $entry;
  }
  
  /**
   * returns the key for this entry.
   * 
   * @see Entry#key()
   * @return string
   */
  public function key()
  {
    assert($this->key !== null);
    return $this->key;
  }
  
  /**
   * getter for the entry
   * 
   * @return Entry
   */
  public function entry()
  {
    assert($this->key !== null);
    return $this->entry;
  }
  
  /**
   * replaces the current entry with a new one
   * 
   * @param  Entry  $entry
   */
  public function swap(Entry $entry)
  {
    assert($this->key !== null);
    $key = $entry->key();
    
    // the key of the new entry must be the same
    if ($key !== $this->key)
      throw new IAEx('keys do not match');
    
    $this->entry = $entry;
  }
}

/** map */
class Map implements 
  ArrayAccess, IteratorAggregate, Countable
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
   * fills the map with entries
   * @param  array<Entry>  $ents
   * @return void
   */
  public function fill(array $ents)
  {
    foreach ($ents as $ent)
      $this->add($ent);
  }
  
  /**
   * add something to the map.
   * should be overriden for typechecks
   * 
   * @param Entry $val
   * @return boolean
   */
  public function add(Entry $val)
  {
    $key = $val->key();
    $ent = $val;
    
    if ($val instanceof Cell)
      $ent = $val->entry();
    
    if (!$this->check($ent))
      throw new IAEx('check failed');
    
    if (!isset ($this->mem[$key])) {
      $this->mem[$key] = $val;
      
      if ($val instanceof Cell)
        $val->map($this);
      
      return true;
    }
    
    return false;
  }
  
  /**
   * same as add() but overrides existing entries
   * 
   * @param  Entry $val
   * @return Entry  the previous Entry or null
   */
  public function put(Entry $val)
  {
    $key = $val->key();
    $prv = null;
    $ent = $val;
    
    if ($val instanceof Cell)
      $ent = $val->entry();
    
    if (!$this->check($ent))
      throw new IAEx('check failed');
    
    if (isset ($this->mem[$key]))
      $prv = $this->mem[$key];
    
    $this->mem[$key] = $val;
    
    return $prv;
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
   * @return Entry
   */
  public function get($key)
  {
    $ent = null;
    
    if (isset ($this->mem[$key]))
      $ent = $this->mem[$key]; // do not deref Cells
    
    return $ent;
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
  
  /**
   * deletes all items of this map
   */
  public function clear() 
  {
    $this->mem = [];
  }
  
  /* ------------------------------------ */
  
  /**
   * checks if a Entry is valid in this map.
   * override this method to achieve a 'Map<Type>' implementation.
   * 
   * @param  Entry $itm
   * @return boolean
   */
  protected function check(Entry $itm)
  {
    return true;
  }
  
  /* ------------------------------------ */
  /* magic */
  
  public function __set($key, $val)
  {
    // override via object-access is not allowed
    throw new RTException('using $map->... to store entries is not allowed');
  }
  
  public function __get($key)
  {
    // forward to $this->get()
    return $this->get($key);
  }
  
  public function __isset($key)
  {
    // forward to $this->has()
    return $this->has($key);
  }
  
  public function __unset($key)
  {
    // forward to $this->delete()
    return $this->delete($key);
  }
  
  /* ------------------------------------ */
  /* ArrayAccess */
  
  public function offsetSet($key, $val)
  {
    // override via array-access is not allowed
    throw new RTException('using $map[...] to store entries is not allowed');
  }
  
  public function offsetGet($key)
  {
    // forward to $this->get()
    return $this->get($key);
  }
  
  public function offsetExists($key)
  {
    // forward to $this->has()
    return $this->has($key);
  }
  
  public function offsetUnset($key)
  {
    // forward to $this->delete()
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
}
