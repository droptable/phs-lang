<?php

namespace phs\ast;

class RequireDecl extends Node
{
  public $php;
  public $expr;
  
  // @var string  the computed path
  public $path;
  
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
