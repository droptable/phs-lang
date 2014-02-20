<?php

namespace phs\ast;

class LNumLit extends Node
{
  public $value;
  
  public function __construct($value)
  {
    $this->value = $value;
  }
}
