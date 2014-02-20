<?php

namespace phs\ast;

class SwitchStmt extends Node
{
  public $test;
  public $cases;
  
  public function __construct($test, $cases)
  {
    $this->test = $test;
    $this->cases = $cases;
  }
}
