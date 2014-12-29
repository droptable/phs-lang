<?php

namespace phs\ast;

// TODO: drop this node, let the parser do this

use phs\Location;

class NestedMods extends Node
{
  // @var array<Token>  modifiers
  public $mods;
  
  // @var array<VarDecl|FnDecl|NestedMods>
  public $members;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param array    $mods
   * @param array    $members
   */
  public function __construct(Location $loc, array $mods, array $members)
  {
    parent::__construct($loc);
    
    $this->mods = $mods;
    $this->members = $members;
  }

  public function __clone()
  {
    if ($this->mods) {
      $mods = $this->mods;
      $this->mods = [];
      
      foreach ($mods as $mod)
        $this->mods[] = clone $mod;  
    }
    
    if ($this->members) {
      $members = $this->members;
      $this->members = [];
      
      foreach ($members as $member)
        $this->members[] = clone $member;  
    }
    
    parent::__clone();
  }
}
