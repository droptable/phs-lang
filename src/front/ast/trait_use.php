<?php

namespace phs\front\ast;

class TraitUse extends Node
{
  public $name;
  public $alias;
  public $items;
  
  public function __construct($name, $alias, $items)
  {
    if ($alias !== null) 
      assert($items === null);
    elseif ($items !== null) 
      assert($alias === null);
    
    $this->name = $name;
    $this->alias = $alias;
    $this->items = $items;
  }  
}
