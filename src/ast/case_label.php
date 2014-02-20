<?php

namespace phs\ast;

class CaseLabel extends Node
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}
