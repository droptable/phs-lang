<?php

namespace phs\ast;

class RequireDecl extends Node
{
  public $php;
  public $expr;
  
  // @var Source  the resolved source
  public $source;
  
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
