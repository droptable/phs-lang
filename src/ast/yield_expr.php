<?php

namespace phs\ast;

class YieldExpr extends Node
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}
