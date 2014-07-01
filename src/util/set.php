<?php

namespace phs\util;

use \Countable;
use \ArrayIterator;
use \IteratorAggregate;

use \InvalidArgumentException as IAException;

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
    if (!$this->check($val))
      throw new IAException('check');
    
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
  
  /**
   * checks if a Entry is valid in this set.
   * override this method to archive a 'Set<Type>' implementation.
   * 
   * @param  mixed $val
   * @return boolean
   */
  protected function check($val)
  {
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
