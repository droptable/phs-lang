<?php

namespace phs\ast;

class UpdateExpr extends Node
{
  public $prefix;
  public $expr;
  public $op;
  
  public function __construct($prefix, $expr, $op)
  {
    $this->prefix = $prefix;
    $this->expr = $expr;
    $this->op = $op;
  }
}
