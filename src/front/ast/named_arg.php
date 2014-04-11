<?php

namespace phs\front\ast;

class NamedArg extends Node
{
  public $name;
  public $expr;
  
  public function __construct($name, $expr)
  {
    $this->name = $name;
    $this->expr = $expr;
  }
}
