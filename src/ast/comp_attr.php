<?php

namespace phs\ast;

class CompAttr extends Node
{
  public $def;
  public $comp;
  
  public function __construct($def, $comp)
  {
    $this->def = $def;
    $this->comp = $comp;
  }
}
