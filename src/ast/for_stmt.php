<?php

namespace phs\ast;

class ForStmt extends Node
{
  public $init;
  public $test;
  public $each;
  public $stmt;
  
  public function __construct($init, $test, $each, $stmt)
  {
    $this->init = $init;
    $this->test = $test;
    $this->each = $each;
    $this->stmt = $stmt;
  }
}
