<?php

namespace phs\ast;

use phs\Location;

class IfaceDecl extends Decl
{
  // @var array<Token>  modifiers
  public $mods;
  
  // @var Ident
  public $id;
  
  // @var array<Ident>  generic arguments
  public $genc;
  
  // @var array<Name>  interfaces
  public $impl;
  
  // @var array<FnDecl>
  public $members;
  
  // @var Scope  member-scope
  public $scope;
  
  /**
   * construct
   *
   * @param Location $loc
   * @param array    $mods
   * @param Ident    $id
   * @param array    $genc
   * @param array    $impl
   * @param array    $members
   */
  public function __construct(Location $loc, array $mods, Ident $id, 
                              array $genc, array $impl, array $members)
  {
    parent::__construct($loc);
    
    $this->mods = $mods;
    $this->id = $id;
    $this->genc = $genc;
    $this->impl = $impl;
    $this->members = $members;
    $this->incomp = $incomp;
  }

  public function __clone()
  {
    if ($this->mods) {
      $mods = $this->mods;
      $this->mods = [];
      
      foreach ($mods as $mod)
        $this->mods[] = clone $mod;  
    }
    
    $this->id = clone $this->id;
    
    if ($this->impl) {
      $impl = $this->impl;
      $this->impl = [];
      
      foreach ($impl as $imp)
        $this->impl[] = clone $imp;  
    }
    
    if ($this->members) {
      $members = $this->members;
      $this->members = [];
      
      foreach ($members as $member)
        $this->members[] = clone $member;  
    }
    
    if ($this->scope)
      $this->scope = clone $this->scope;
    
    parent::__clone();
  }
}
