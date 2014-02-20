<?php

namespace phs\ast;

class CondExpr extends Node
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
