<?php

namespace phs\ast;

class YieldStmt extends Node
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}
