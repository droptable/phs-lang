<?php

namespace phs\front\ast;

class IfStmt extends Stmt
{
  public $test;
  public $stmt;
  public $elsifs;
  public $els;
  
  public function __construct($test, $stmt, $elsifs, $els)
  {
    $this->test = $test;
    $this->stmt = $stmt;
    $this->elsifs = $elsifs;
    $this->els = $els;
  }
}
