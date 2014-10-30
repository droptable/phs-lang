<?php

namespace phs\ast;

class ObjPair extends Node
{
  public $key;
  public $arg;
  
  public function __construct($key, $arg)
  {
    $this->key = $key;
    $this->arg = $arg;
  }

  public function __clone()
  {
    $this->key = clone $this->key;
    $this->arg = clone $this->arg;
    
    parent::__clone();
  }
}
