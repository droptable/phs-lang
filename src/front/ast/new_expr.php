<?php

namespace phs\front\ast;

class NewExpr extends Expr
{
  public $name;
  public $args;
  
  public function __construct($name, $args)
  {
    $this->name = $name;
    $this->args = $args;
  }

  public function __clone()
  {
    $this->name = clone $this->name;
    
    if ($this->args) {
      $args = $this->args;
      $this->args = [];
      
      foreach ($args as $arg)
        $this->args[] = clone $arg;
    }
    
    parent::__clone();
  }
}
