<?php

namespace phs\ast;

class ExprStmt extends Node
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}
