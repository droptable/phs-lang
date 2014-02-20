<?php

namespace phs\ast;

class TypeId extends Node
{
  public $type;
  
  public function __construct($type)
  {
    $this->type = $type;
  }
}
