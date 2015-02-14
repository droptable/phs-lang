<?php

namespace phs\ast;

use phs\Location;

class EnumDecl extends Decl
{
  public $mods;
  public $id;
  public $items;
  
  /**
   * constructor
   *
   * @param Location   $loc
   * @param array|null $mods
   * @param Ident|null $id
   * @param array|null $items
   */
  public function __construct(Location $loc, array $mods = null, 
                              Ident $id = null, array $items = null)
  {
    parent::__construct($loc);
    
    $this->mods = $mods;
    $this->id = $id;
    $this->items = $items;
  }

  public function __clone()
  {
    if ($this->mods) {
      $mods = $this->mods;
      $this->mods = [];
      
      foreach ($mods as $mod)
        $this->mods[] = clone $mod;  
    }
    
    if ($this->id)
      $this->id = clone $this->id;
    
    if ($this->items) {
      $items = $this->items;
      $this->items = [];
      
      foreach ($items as $item)
        $this->items[] = clone $item;
    }
    
    parent::__clone();
  }
}
