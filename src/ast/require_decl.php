<?php

namespace phs\ast;

class RequireDecl extends Node
{
  public $php;
  public $expr;
  
  public function __construct($php, $expr)
  {
    $this->php = $php;
    $this->expr = $expr;
  }
}
