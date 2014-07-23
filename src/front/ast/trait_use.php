<?php

namespace phs\front\ast;

class TraitUse extends Node
{
  public $name;
  public $items;
  
  public function __construct($name, $items)
  {    
    $this->name = $name;
    $this->items = $items;
  }  
}
