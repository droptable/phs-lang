<?php

namespace phs\front\ast;

class CallExpr extends Expr
{
  public $callee;
  public $args;
  
  public function __construct($callee, $args)
  {
    $this->callee = $callee;
    $this->args = $args;
  }
}
