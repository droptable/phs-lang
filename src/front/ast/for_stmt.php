<?php

namespace phs\front\ast;

class ForStmt extends Stmt
{
  public $init;
  public $test;
  public $each;
  public $stmt;
  
  public $lexical = false;
  public $scope;
  
  public function __construct($init, $test, $each, $stmt)
  {
    $this->init = $init;
    $this->test = $test;
    $this->each = $each;
    $this->stmt = $stmt;
  }
}
