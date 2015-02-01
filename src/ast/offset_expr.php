<?php

namespace phs\ast;

use phs\Symbol;
use phs\Location;

class OffsetExpr extends Expr
{
  // @var Expr
  public $object;
  
  // @var Expr
  public $offset;
  
  // @var Symbol
  public $symbol;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Expr     $object
   * @param Expr     $offset
   */
  public function __construct(Location $loc, Expr $object, Expr $offset)
  {
    parent::__construct($loc);
    
    $this->object = $object;
    $this->offset = $offset;
  }

  public function __clone()
  {
    $this->object = clone $this->object;
    $this->offset = clone $this->offset;
    
    parent::__clone();
  }
}
