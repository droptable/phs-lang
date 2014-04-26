<?php

namespace phs\front\ast;

class AttrItem extends Node 
{
  public $name;
  public $value;
  
  public function __construct($name, $value)
  {
    $this->name = $name;
    $this->value = $value;
  }
}
