<?php

namespace phs\ast;

class UnaryExpr extends Node
{
  public $op;
  public $expr;
  
  public function __construct($op, $expr)
  {
    $this->op = $op;
    $this->expr = $expr;
  }
}
