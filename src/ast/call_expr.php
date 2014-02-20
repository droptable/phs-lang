<?php

namespace phs\ast;

class CallExpr extends Node
{
  public $callee;
  public $args;
  
  public function __construct($callee, $args)
  {
    $this->callee = $callee;
    $this->args = $args;
  }
}
