<?php

namespace phs\ast;

class SwitchStmt extends Stmt
{
  public $test;
  public $cases;
  
  public function __construct($test, $cases)
  {
    $this->test = $test;
    $this->cases = $cases;
  }
}
