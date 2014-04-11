<?php

namespace phs\front\ast;

class ReturnStmt extends Stmt
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}
