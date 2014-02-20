<?php

namespace phs\ast;

class ArrayGen extends Node
{
  public $expr;
  public $init;
  public $each;
  
  public function __construct($expr, $init, $each)
  {
    $this->expr = $expr;
    $this->init = $init;
    $this->each = $each;
  }
}
