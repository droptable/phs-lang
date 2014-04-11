<?php

namespace phs\front\ast;

class AssertStmt extends Stmt
{
  public $expr;
  public $message;
  
  public function __construct($expr, $message)
  {
    $this->expr = $expr;
    $this->message = $message;
  }
}
