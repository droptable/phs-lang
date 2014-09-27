<?php

namespace phs\front\ast;

class RequireDecl extends Node
{
  public $php;
  public $expr;
  
  public function __construct($php, $expr)
  {
    $this->php = $php;
    $this->expr = $expr;
  }

  public function __clone()
  {
    $this->expr = clone $this->expr;
    
    parent::__clone();
  }
}
