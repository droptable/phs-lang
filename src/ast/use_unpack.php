<?php

namespace phs\ast;

use phs\Location;

class UseUnpack extends Node
{
  // @var Name
  public $base;
  
  // @var array<Name|UseAlias|UseUnpack>
  public $items;
  
  /**
   * constructor
   *
   * @param Location $loc 
   * @param Name     $base
   * @param array    $items
   */
  public function __construct(Location $loc, Name $base, array $items)
  {
    parent::__construct($loc);
    
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
