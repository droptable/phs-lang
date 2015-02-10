<?php

namespace phs\ast;

use phs\Location;

class YieldExpr extends Expr
{
  // @var Expr
  public $key;
  
  // @var Expr
  public $arg;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Expr     $key
   * @param Expr     $arg
   */
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
