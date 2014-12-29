<?php

namespace phs\ast;

use phs\Location;

class ArrLit extends Expr
{
  // @var array<Expr>
  public $items;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param array    $items
   */
  public function __construct(Location $loc, array $items)
  {
    parent::__construct($loc);
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
