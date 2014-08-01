<?php

namespace phs\front\ast;

class DoStmt extends Stmt
{
  public $stmt;
  public $test;
  
  public function __construct($stmt, $test)
  {
    $this->stmt = $stmt;
    $this->test = $test;
  }
}
