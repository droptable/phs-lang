<?php

namespace phs\ast;

use phs\Location;

class CastExpr extends Expr
{
  // @var Expr  left-hand-side
  public $expr;
  
  // @var TypeName
  public $type;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Expr     $expr
   * @param TypeName $type
   */
  public function __construct(Location $loc, Expr $expr, TypeName $type)
  {
    parent::__construct($loc);
    
    $this->expr = $expr;
    $this->type = $type;
  }

  public function __clone()
  {
    $this->expr = clone $this->expr;
    $this->type = clone $this->type;
    
    parent::__clone();
  }
}
