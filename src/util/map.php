<?php

namespace phs\util;

use \ArrayAccess;
use \ArrayIterator;
use \IteratorAggregate;
use \Countable;

use \RuntimeException as RTException;
use \InvalidArgumentException as IAException;

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

/** 
 * cell: usefull for 'incomplete' or 'placeholder' entries
 * 
 * note: a cell creates a circular reference to the map it is assigned to!
 */
final class Cell implements Entry
{
  // @var Map
  private $map;
  
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
    $this->map = null;
    $this->key = $entry->key();
    $this->entry = $entry;
  }
  
  /**
   * sets the map of this cell
   * @param  Map    $map
   */
  public function map(Map $map = null) 
  {
    if ($this->key === null)
      throw new RTException('invalid cell');
    
    $this->map = $map;
  }
  
  /**
   * returns the key for this entry.
   * 
   * @see Entry#key()
   * @return string
   */
  public function key()
  {
    if ($this->key === null)
      throw new RTException('invalid cell');
    
    return $this->key;
  }
  
  /**
   * getter for the entry
   * 
   * @return Entry
   */
  public function entry()
  {
    if ($this->key === null)
      throw new RTException('invalid cell');
    
    return $this->entry;
  }
  
  /**
   * replaces the current entry with a new one
   * 
   * @param  Entry  $entry
   */
  public function swap(Entry $entry)
  {
    if ($this->key === null)
      throw new RTException('invalid cell');
    
    if (!$this->check($entry))
      throw new IAException('cell entry check failed');
    
    $key = $entry->key();
    
    // the key of the new entry must be the same
    if ($key !== $this->key)
      throw new IAException('invalid key');
    
    $this->entry = $entry;
  }
  
  /**
   * checks if the map accepts an entry
   * @param  Entry  $entry
   * @return boolean
   */
  private function check(Entry $entry) 
  {
    // only check if there is a map
    return !$this->map || $this->map->check_cell_entry($entry);
  }
  
  /**
   * replaces the cell with the actual entry.
   * 
   * the cell is no longer linked to the map or the entry after this call!
   */
  public function flush()
  {
    if ($this->key === null)
      throw new RTException('invalid cell');
    
    if (!$this->map) return;
    
    $this->map->put($this->entry);
    
    $this->map = null;
    $this->key = null;
    $this->entry = null;
  }
  
}

/** map */
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
      throw new IAException('check');
    
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
      throw new IAException('check');
    
    if (isset ($this->mem[$key]))
      $prv = $this->mem[$key];
    
    $this->mem[$key] = $val;
    
    if ($val instanceof Cell)
      $val->map($this);
    
    if ($prv instanceof Cell)
      $prv->map(null);
    
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
      $ent = $this->mem[$key];
      
      if ($ent instanceof Cell)
        $ent->map(null);
      
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
    foreach ($this->mem as $key => $val)
      if ($val instanceof Cell)
        $val->map(null);
      
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
  
  // this method is for cells, don't use it.
  public function check_cell_entry(Entry $ent) 
  {
    return $this->check($ent);
  }
  
  /* ------------------------------------ */
  /* magic */
  
  public function __set($key, $val)
  {
    // override via object-access is not allowed
    throw new RTException('objset');
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
    throw new RTException('arrset');
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
