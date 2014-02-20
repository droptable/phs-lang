<?php

namespace phs\ast;

class SNumLit extends Node
{
  public $value;
  public $suffix;
  
  public function __construct($value, $suffix)
  {
    $this->value = $value;
    $this->suffix = $suffix;
  }
}
