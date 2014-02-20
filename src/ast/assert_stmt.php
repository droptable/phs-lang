<?php

namespace phs\ast;

class AssertStmt extends Node
{
  public $expr;
  public $message;
  
  public function __construct($expr, $message)
  {
    $this->expr = $expr;
    $this->message = $message;
  }
}
