<?php

namespace phs\ast;

use phs\Token;
use phs\Location;

class TraitUseItem extends Node
{
  // @var Ident
  public $id;
  
  // @var array<Token>
  public $mods;
  
  // @var Ident
  public $alias;
  
  /**
   * constructor
   *
   * @param Location   $loc
   * @param Ident      $id
   * @param array|null $mods
   * @param Ident|null $alias
   */
  public function __construct(Location $loc, Ident $id, array $mods = null, 
                              Ident $alias = null)
  {
    parent::__construct($loc);
    
    $this->id = $id;
    $this->mods = $mods;
    $this->alias = $alias;
  }

  public function __clone()
  {
    $this->id = clone $this->id;
    
    if ($this->mods) {
      $mods = $this->mods;
      $this->mods = [];
      
      foreach ($mods as $mod)
        $this->mods[] = clone $mod;  
    }
    
    if ($this->alias)
      $this->alias = clone $this->alias;
    
    parent::__clone();
  }
}
