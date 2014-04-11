<?php

namespace phs\front\ast;

class CondExpr extends Expr
{
  public $test;
  public $then;
  public $els;
  
  public function __construct($test, $then, $els)
  {
    $this->test = $test;
    $this->then = $then;
    $this->els = $els;
  }
}
