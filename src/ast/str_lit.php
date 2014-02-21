<?php

namespace phs\ast;

class StrLit extends Node
{
  public $value;
  public $flag;
  
  public function __construct($value, $flag)
  {
    $this->value = (string)$value;
    $this->flag = $flag;
  }
}
