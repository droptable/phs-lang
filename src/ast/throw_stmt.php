<?php

namespace phs\ast;

class ThrowStmt extends Stmt
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}
