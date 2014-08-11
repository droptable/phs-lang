<?php

namespace phs\front\ast;

class TupleExpr extends Expr
{
  public $seq;
  
  public function __construct($seq)
  {
    $this->seq = $seq;
  }
}
