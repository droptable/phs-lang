<?php

namespace phs\ast;

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

  public function __clone()
  {
    $this->test = clone $this->test;
    
    if ($this->then)
      $this->then = clone $this->then;
    
    $this->els = clone $this->els;
  
    parent::__clone();
  }
}
