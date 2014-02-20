<?php

namespace phs\ast;

class DNumLit extends Node
{
  public $value;
  
  public function __construct($value)
  {
    $this->value = $value;
  }
}
