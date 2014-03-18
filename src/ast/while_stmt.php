<?php

namespace phs\ast;

class WhileStmt extends Stmt
{
  public $test;
  public $stmt;
  
  public function __construct($test, $stmt)
  {
    $this->test = $test;
    $this->stmt = $stmt;
  }
}
