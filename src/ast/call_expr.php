<?php

namespace phs\ast;

class CallExpr extends Expr
{
  public $callee;
  public $args;
  
  public function __construct($callee, $args)
  {
    $this->callee = $callee;
    $this->args = $args;
  }
  
  public function __clone()
  {
    $this->callee = clone $this->callee;
    
    if ($this->args) {
      $args = $this->args;
      $this->args = [];
      
      foreach ($args as $arg)
        $this->args[] = clone $arg;
    }
    
    parent::__clone();
  }
}
