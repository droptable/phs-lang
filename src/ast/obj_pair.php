<?php

namespace phs\ast;

class ObjPair extends Node
{
  public $key;
  public $value;
  
  public function __construct($key, $value)
  {
    $this->key = $key;
    $this->value = $value;
  }

  public function __clone()
  {
    $this->key = clone $this->key;
    $this->value = clone $this->value;
    
    parent::__clone();
  }
}
