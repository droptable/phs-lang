<?php

namespace phs\front\ast;

class ThrowStmt extends Stmt
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}