<?php

namespace phs\ast;

// unused in parser v2 (replaced by standard streams)
use phs\Location;

class PrintStmt extends Stmt
{
  // @var array<Expr>
  public $seq;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param array    $expr
   */
  public function __construct(Location $loc, array $seq)
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
