<?php

namespace phs\lang;

require_once __DIR__ . '/../util/dict.php';

use \ArrayAccess;
use \ArrayIterator;
use \IteratorAggregate;
use \InvalidArgumentException as IAEx;

use phs\util\Dict;

class BuiltInDict extends Dict
{}

class BuiltInList implements ArrayAccess, IteratorAggregate
{
  // internal memory
  private $mem;
  
  /**
   * constructor
   *
   * @param List|array $init
   */
  public function __construct($init = null)
  {
    // init from array
    if (is_array($init))
      $this->mem = array_values($init);
    
    // init from a list
    elseif ($init instanceof self)
      foreach ($init as $val)
        $this->mem = $val;
      
    // init as empty
    else
      $this->mem = [];
  }
  
  /**
   * __clone
   *
   * @return void
   */
  public function __clone()
  {
    $dup = [];
    
    foreach ($this->mem as $idx => &$val)
      $dup[$idx] = clone $val;
    
    $this->mem = $dup;
  }
  
  /**
   * returns the current size of this list
   *
   * @return int
   */
  public function size()
  {
    return count($this->mem);
  }
  
  /**
   * ArrayAccess#offsetGet()
   *
   * @param  int $off
   * @return mixed
   */
  public function offsetGet($off)
  {
    if ($off >= $this->size())
      return null;
    
    return $this->mem[$off];
  }
  
  /**
   * ArrayAccess#offsetSet()
   *
   * @param  int $off
   * @param  mixed $val
   * @return void
   */
  public function offsetSet($off, $val)
  {
    if ($off === null)
      array_push($this->mem, $val);
    
    elseif (!is_int($off))
      throw new IAEx('invalid offset');
    
    elseif ($off < 0)
      throw new IAEx('negative offset');
    
    elseif ($off > $this->size())
      throw new IAEx('offset is too large');
    
    else
      $this->mem[$off] = $val;
  }
  
  /**
   * ArrayAccess#offsetExists()
   *
   * @param  int $off
   * @return boolean
   */
  public function offsetExists($off)
  {
    if (!is_int($off))
      throw new IAEx('invalid offset');
    
    return isset ($this->mem[$off]);
  }
  
  /**
   * ArrayAccess#offsetUnset()
   *
   * @param  int $off
   * @return void
   */
  public function offsetUnset($off)
  {
    if (!is_int($off))
      throw new IAEx('invalid offset');
    
    elseif ($off < 0 || $off >= $this->size())
      throw new IAEx('out of range');
    
    else
      unset ($this->mem[$off]);
  }
  
  /**
   * IteratorAggregate#getIterator()
   *
   * @return ArrayIterator
   */
  public function getIterator()
  {
    return new ArrayIterator($this->mem);
  }
}
