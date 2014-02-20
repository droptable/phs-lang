<?php

namespace phs\ast;

class AttrVal extends Node
{
  public $name;
  public $sub;
  
  public function __construct($name, $sub = null)
  {
    $this->name = $name;
    $this->sub = $sub;
  }  
}
