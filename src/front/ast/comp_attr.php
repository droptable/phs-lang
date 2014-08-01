<?php

namespace phs\front\ast;

class CompAttr extends Node
{
  public $attr;
  public $comp;
  
  public function __construct($attr, $comp)
  {
    $this->attr = $attr;
    $this->comp = $comp;
  }
}
