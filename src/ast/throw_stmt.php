<?php

namespace phs\ast;

class ThrowStmt extends Node
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}
