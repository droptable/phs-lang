<?php

namespace phs\front\ast;

class PrintStmt extends Stmt
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
      
      foreach ($expr as $item)
        $this->expr[] = clone $item;
    }
    
    parent::__clone();
  }
}
