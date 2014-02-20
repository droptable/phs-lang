<?php

namespace phs\ast;

class ReturnStmt extends Node
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}
