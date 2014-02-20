<?php

namespace phs\ast;

class StrLit extends Node
{
  public $value;
  
  public function __construct($value)
  {
    $this->value = (string)$value;
  }
}
