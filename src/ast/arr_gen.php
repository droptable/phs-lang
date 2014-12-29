<?php

namespace phs\ast;

use phs\Location;

class ArrGen extends Node
{
  // @var Expr  generator expression
  public $expr;
  
  // @var Ident  generator handle
  public $init;
  
  // @var Expr  iterator expression
  public $each;
  
  /**
   * constructor
   *
   * @param Location $loc 
   * @param Expr     $expr
   * @param Ident    $init
   * @param Expr     $each
   */
  public function __construct(Location $loc, Expr $expr, Ident $init, Expr $each)
  {
    parent::__construct($loc);
    
    $this->expr = $expr;
    $this->init = $init;
    $this->each = $each;
  }
  
  public function __clone()
  {
    $this->expr = clone $this->expr;
    $this->init = clone $this->init;
    $this->each = clone $this->each;
    
    parent::__clone();
  }
}
