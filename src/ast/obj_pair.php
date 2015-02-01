<?php

namespace phs\ast;

use phs\Location;

class ObjPair extends Node
{
  // @var Expr|Ident|StrLit  key
  public $key;
  
  // @var Expr  value
  public $arg;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Node     $key
   * @param Expr     $arg
   */
  public function __construct(Location $loc, Node $key, Expr $arg)
  {
    parent::__construct($loc);
    
    $this->key = $key;
    $this->arg = $arg;
  }

  public function __clone()
  {
    $this->key = clone $this->key;
    $this->arg = clone $this->arg;
    
    parent::__clone();
  }
}
