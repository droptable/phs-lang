<?php

namespace phs\ast;

use phs\Location;

class NamedArg extends Node
{
  // @var Ident
  public $id;
  
  // @var Expr
  public $expr;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Ident    $id
   * @param Expr     $expr
   */
  public function __construct(Location $loc, Ident $id, Expr $expr)
  {
    parent::__construct($loc);
    
    $this->id = $id;
    $this->expr = $expr;
  }

  public function __clone()
  {
    $this->id = clone $this->id;
    $this->expr = clone $this->expr;
    
    parent::__clone();
  }
}
