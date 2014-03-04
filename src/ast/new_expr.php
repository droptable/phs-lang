<?php

namespace phs\ast;

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
