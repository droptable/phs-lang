<?php

namespace phs\ast;

use phs\Location;

class TypeDecl extends Decl 
{
  public $id;
  public $decl;
  
  /**
   * constructor
   *
   * @param Location   $loc
   * @param array|null $mods
   * @param Ident      $id
   * @param TypeName   $decl
   */
  public function __construct(Location $loc, array $mods = null,
                              Ident $id, TypeName $decl)
  {
    parent::__construct($loc);
    
    $this->id = $id;
    $this->mods = $mods;
    $this->decl = $decl;
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
    
    $this->decl = clone $this->decl;
  }
}
