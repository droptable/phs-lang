<?php

namespace phs\ast;

class RestArg extends Node
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}
