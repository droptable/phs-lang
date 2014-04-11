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
}
