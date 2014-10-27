<?php

namespace phs\ast;

class DoStmt extends Stmt
{
  public $stmt;
  public $test;
  
  public function __construct($stmt, $test)
  {
    $this->stmt = $stmt;
    $this->test = $test;
  }

  public function __clone()
  {
    $this->stmt = clone $this->stmt;
    $this->test = clone $this->test;
    
    parent::__clone();
  }
}
