<?php

namespace phs\ast;

use phs\Token;
use phs\Scope;
use phs\Location;

class TraitDecl extends Decl
{
  // @var array<Token>
  public $mods;
  
  // @var Ident
  public $id;
  
  // @var array<TraitUse>
  public $traits;
  
  // @var array<FnDecl|VarDecl>
  public $members;
  
  // @var Scope  member-scope
  public $scope;
  
  /**
   * constructor
   *
   * @param Location   $loc
   * @param array|null $mods
   * @param Ident      $id
   * @param array|null $traits
   * @param array|null $members
   */
  public function __construct(Location $loc, array $mods = null, Ident $id, 
                              array $traits = null, array $members = null)
  {
    parent::__construct($loc);
    
    $this->mods = $mods;
    $this->id = $id;
    $this->traits = $traits;
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
    
    $this->id = clone $this->id;
        
    if ($this->traits) {
      $traits = $this->traits;
      $this->traits = [];
      
      foreach ($traits as $trait)
        $this->traits[] = clone $trait;      
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
