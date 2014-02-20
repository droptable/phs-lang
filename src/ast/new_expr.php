<?php

namespace phs\ast;

class NewExpr extends Node
{
  public $name;
  public $args;
  
  public function __construct($name, $args)
  {
    $this->name = $name;
    $this->args = $args;
  }
}
