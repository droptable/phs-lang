<?php

namespace phs\ast;

class AttrDef extends Node
{
  public $name;
  public $value;
  
  public function __construct($name, $value = null)
  {
    $this->name = $name;
    $this->value = $value;
  }
}
