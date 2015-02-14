<?php

namespace phs\ast;

use phs\Location;

class ExprStmt extends Stmt
{
  // @var array<Expr>
  public $seq;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param array    $seq
   */
  public function __construct(Location $loc, array $seq = null)
  {
    parent::__construct($loc);
    $this->seq = $seq;
  }

  public function __clone()
  {
    if ($this->seq) {
      $seq = $this->seq;
      $this->seq = [];
      
      foreach ($seq as $expr)
        $this->seq[] = clone $expr;
    }
    
    parent::__clone();
  }
}
