<?php

namespace phs\ast;

class SpreadExpr extends Node
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}
