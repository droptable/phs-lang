<?php

namespace phs\ast;

class TraitUse extends Node
{
  public $name;
  public $items;
  
  public function __construct($name, $items)
  {    
    $this->name = $name;
    $this->items = $items;
  }  

  public function __clone()
  {
    $this->name = clone $this->name;
    
    if ($this->items) {
      $items = $this->items;
      $this->items = [];
      
      foreach ($items as $item)
        $this->items[] = clone $item;
    }
    
    parent::__clone();
  }
}
