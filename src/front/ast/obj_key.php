<?php

namespace phs\front\ast;

class ObjKey extends Node
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}
