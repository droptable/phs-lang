<?php

namespace phs\front\ast;

class ForInPair extends Node
{
  public $key;
  public $arg;
  
  public function __construct($key, $arg)
  {
    $this->key = $key;
    $this->arg = $arg;
  }
}
