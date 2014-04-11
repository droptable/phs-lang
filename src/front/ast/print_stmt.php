<?php

namespace phs\front\ast;

class PrintStmt extends Stmt
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}
