<?php

namespace phs\util;

// for interface Entry
require_once 'map.php';

use \Countable;
use \ArrayIterator;
use \IteratorAggregate;

use \InvalidArgumentException as IAException;

class Set implements 
  IteratorAggregate, Countable
{
  // memory (array)
  protected $mem = [];
  
  /**
   * constructor
   */
  public function __construct()
  {
    // empty
  }
  
  /**
   * fills the set with entries
   * @param  array<Entry> $ents
   * @return void
   */
  public function fill($ents)
  {
    foreach ($ents as $ent)
      $this->add($ent);
  }
  
  /**
   * add something to the map.
   * should be overridden for typechecks
   * 
   * @param mixed $val
   * @return boolean
   */
  public function add($val)
  {
    if (!$this->check($val))
      throw new IAException('check');
    
    if ($this->has($val))
      return false;
    
    $this->mem[] = $val;
    return true;
  }
  
  /**
   * check if an item exists
   * should be overridden for typechecks
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
   * should be overridden for typechecks
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
  
  /**
   * returns the first item in the set.
   * the item gets removed.
   *
   * @return mixed
   */
  public function shift()
  {
    return array_shift($this->mem);
  }
  
  /**
   * returns the top-most item in the set.
   * the item gets removed.
   *
   * @return mixed
   */
  public function pop()
  {
    return array_pop($this->mem);
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

/** loose set: supports a user-defined compare method */
class LooseSet extends Set
{
  /**
   * @see Set#has()
   * @param  mixed  $val
   * @return boolean
   */
  public function has($val)
  {
    return $this->find($val) > -1;
  }
  
  /**
   * @see Set#delete()
   * @param  mixed $val
   * @return boolean
   */
  public function delete($val)
  {
    $idx = $this->find($val);
    
    if ($idx === -1)
      return false;
    
    array_splice($this->mem, $idx, 1);
    return true;
  }
  
  /**
   * searches for a value using the compare-method
   * @param  mixed $val
   * @return int  the index in the memory-array
   */
  protected function find($val)
  {
    for ($i = 0, $c = count($this->mem); $i < $c; ++$i)
      if ($this->compare($val, $this->mem[$i]))
        return $i;
      
    return -1;
  }
  
  /**
   * compare-method.
   * feel free to override this method in subclasses
   * 
   * @param  mixed $a
   * @param  mixed $b
   * @return boolean
   */
  protected function compare($a, $b)
  {
    // loose compare by value (default)
    return $a == $b;
  }
}

/** entry-set: supports Entry as value */
class EntrySet extends LooseSet
{
  /**
   * @see Set#has()
   * @param  mixed  $val
   * @return boolean
   */
  public function has($val)
  {
    // early abort on failed typecheck
    return $val instanceof Entry && parent::has($val);  
  }
  
  /**
   * @see Set#check()
   * @param  mixed $val
   * @return boolean
   */
  protected function check($val)
  {
    // if you override this method, make sure that 
    // the value also implements the Entry interface. 
    // otherwise compare() may produce unexpected results.
    return $val instanceof Entry;
  }
  
  /**
   * @see LooseSet#compare()
   * @param  mixed $a
   * @param  mixed $b
   * @return boolean
   */
  protected function compare($a, $b)
  {
    // strict compare by value or by key
    return $a === $b || $a->key() === $b->key();
  }
}
