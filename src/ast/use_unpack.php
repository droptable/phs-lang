<?php

namespace phs\ast;

class UseUnpack extends Node
{
  public $base;
  public $items;
  
  public function __construct($base, $items)
  {
    $this->base = $base;
    $this->items = $items;
  }

  public function __clone()
  {
    $this->base = clone $this->base;
    
    if ($this->items) {
      $items = $this->items;
      $this->items = [];
      
      foreach ($items as $item)
        $this->items[] = clone $item;
    }
        
    parent::__clone();  
  }
}
