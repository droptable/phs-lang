<?php

namespace phs\util;

use \Countable;

/** stack */
class Stack implements Countable
{
  // memory
  private $mem = [];
  
  /**
   * push a value
   * 
   * @param  mixed $val
   */
  public function push($val)
  {
    array_push($this->mem, $val);
  }
  
  /**
   * pop a value
   * 
   * @return mixed
   */
  public function pop()
  {
    return array_pop($this->mem);
  }
  
  // Countable
  public function count()
  {
    return count($this->mem);
  }
}
