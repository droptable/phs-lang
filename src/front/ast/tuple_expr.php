<?php

namespace phs\front\ast;

class TupleExpr extends Expr
{
  public $seq;
  
  public function __construct($seq)
  {
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
