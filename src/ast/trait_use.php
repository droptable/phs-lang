<?php

namespace phs\ast;

use phs\Location;

class TraitUse extends Node
{
  // @var Name
  public $name;
  
  // @var array<TraitUseItem>
  public $items;
  
  /**
   * constructor
   *
   * @param Location   $loc 
   * @param Name       $name
   * @param array|null $items
   */
  public function __construct(Location $loc, Name $name, array $items = null)
  {    
    parent::__construct($loc);
    
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
