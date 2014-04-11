<?php

namespace phs\front\ast;

class DoStmt extends Stmt
{
  public $stmt;
  public $expr;
  
  public function __construct($stmt, $expr)
  {
    $this->stmt = $stmt;
    $this->expr = $expr;
  }
}
