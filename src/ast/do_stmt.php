<?php

namespace phs\ast;

class DoStmt extends Node
{
  public $stmt;
  public $expr;
  
  public function __construct($stmt, $expr)
  {
    $this->stmt = $stmt;
    $this->expr = $expr;
  }
}
