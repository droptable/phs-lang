<?php

namespace phs\ast;

use phs\Location;

abstract class Node
{
  // @var Location
  public $loc;
  
  // string-represetation of this node-name
  private $kind = null;  
  
  /**
   * constructor
   *
   * @param Location $loc
   */
  public function __construct(Location $loc)
  {
    $this->loc = $loc;
  }
  
  /**
   * set/get the kind of this node
   * 
   * @param  string $set
   * @return string
   */
  public function kind($set = null)
  {
    if ($set) 
      $this->kind = $set;
    else {
      if (!$this->kind) {
        $name = get_class($this);
        $name = substr(strrchr($name, "\\"), 1);
        $this->kind = $name;
      }
      
      return $this->kind;
    }
  }

  public function __clone()
  {
    if ($this->loc)
      $this->loc = clone $this->loc;
  }
}
