<?php

namespace phs\util;

use \Countable;
use \ArrayIterator;
use \IteratorAggregate;

class Set implements IteratorAggregate, Countable
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
   * @param mixed $val
   * @return boolean
   */
  public function add($val)
  {
    if (in_array($val, $this->mem, true))
      return false;
    
    $this->mem[] = $val;
    return true;
  }
  
  /**
   * check if an item exists
   * should be overriden for typechecks
   * 
   * @param  mixed  $val
   * @return boolean
   */
  public function has($val)
  {
    return in_array($val, $this->mem, true);
  }
  
  /**
   * deletes an item.
   * should be overriden for typechecks
   * 
   * @param  mixed $val
   * @return boolean  true if the the item existed, false otherwise
   */
  public function delete($val)
  {
    $idx = array_search($val, $this->mem, true);
    
    if ($idx === false)
      return false;
    
    array_splice($this->mem, $idx, 1);
    return true;
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
