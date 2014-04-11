<?php

namespace phs\front\ast;

class ExprStmt extends Stmt
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}
