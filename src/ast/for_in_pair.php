<?php

namespace phs\ast;

// unused in parser v2

use phs\Location;

class ForInPair extends Node
{
  public $key;
  public $arg;
  
  public function __construct(Location $loc, Expr $key, Expr $arg)
  {
    parent::__construct($loc);
    
    $this->key = $key;
    $this->arg = $arg;
  }

  public function __clone()
  {
    if ($this->key)
      $this->key = clone $this->key;
    
    $this->arg = clone $this->arg;
  
    parent::__clone();
  }
}
