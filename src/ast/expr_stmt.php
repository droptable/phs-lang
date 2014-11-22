<?php

namespace phs\ast;

class ExprStmt extends Stmt
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }

  public function __clone()
  {
    if ($this->expr) {
      $expr = $this->expr;
      $this->expr = []; // it's a sequence
      
      foreach ($expr as $node)
        $this->expr[] = clone $node;
    }
    
    parent::__clone();
  }
}
