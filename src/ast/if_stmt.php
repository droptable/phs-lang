<?php

namespace phs\ast;

class IfStmt extends Stmt
{
  public $expr;
  public $stmt;
  public $elsifs;
  public $els;
  
  public function __construct($expr, $stmt, $elsifs, $els)
  {
    $this->expr = $expr;
    $this->stmt = $stmt;
    $this->elsifs = $elsifs;
    $this->els = $els;
  }
}
