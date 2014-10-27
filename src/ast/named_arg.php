<?php

namespace phs\ast;

class NamedArg extends Node
{
  public $name;
  public $expr;
  
  public function __construct($name, $expr)
  {
    $this->name = $name;
    $this->expr = $expr;
  }

  public function __clone()
  {
    $this->name = clone $this->name;
    $this->expr = clone $this->expr;
    
    parent::__clone();
  }
}
