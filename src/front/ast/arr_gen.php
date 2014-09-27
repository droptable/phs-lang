<?php

namespace phs\front\ast;

class ArrGen extends Node
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
  
  public function __clone()
  {
    $this->expr = clone $this->expr;
    $this->init = clone $this->init;
    $this->each = clone $this->each;
    
    parent::__clone();
  }
}
