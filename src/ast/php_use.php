<?php

namespace phs\ast;

class PhpUse extends Node
{
  public $items;
  
  public function __construct($items)
  {
    $this->items = $items;
  }

  public function __clone()
  {
    if ($this->items) {
      $items = $this->items;
      $this->items = [];
      
      foreach ($items as $item)
        $this->items[] = clone $item;
    }
    
    parent::__clone();
  }
}
