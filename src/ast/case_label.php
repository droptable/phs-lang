<?php

namespace phs\ast;

use phs\Location;

class CaseLabel extends Node
{
  // @var Expr  condition
  public $expr;
  
  /**
   * constructor
   *
   * @param Location  $loc
   * @param Expr|null $expr
   */
  public function __construct(Location $loc, Expr $expr = null)
  {
    parent::__construct($loc);
    $this->expr = $expr;
  }

  public function __clone()
  {
    if ($this->expr)
      $this->expr = clone $this->expr;
    
    parent::__clone();
  }
}
