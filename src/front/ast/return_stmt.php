<?php

namespace phs\front\ast;

class ReturnStmt extends Stmt
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }

  public function __clone()
  {
    if ($this->expr)
      $this->expr = clone $this->expr;
    
    parent::__clone();
  }
}
