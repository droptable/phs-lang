<?php

namespace phs\ast;

use phs\Source;
use phs\Location;

class RequireDecl extends Node
{
  // @var bool  unused
  public $php;
  
  // @var Expr
  public $expr;
  
  // @var Source
  public $source;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Expr     $expr
   */
  public function __construct(Location $loc, /* $php, */ Expr $expr)
  {
    parent::__construct($loc);
    
    $this->php = $php;
    $this->expr = $expr;
  }

  public function __clone()
  {
    $this->expr = clone $this->expr;
    
    parent::__clone();
  }
}
