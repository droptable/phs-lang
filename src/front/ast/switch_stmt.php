<?php

namespace phs\front\ast;

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
