<?php

namespace phs\ast;

use phs\Scope;
use phs\MemberScope;
use phs\Location;

class ClassDecl extends Decl
{
  // @var array<Token>  modifiers
  public $mods;
  
  // @var Ident
  public $id;
  
  // @var array<Ident>  generic arguments
  public $genc;
  
  // @var Name  super class
  public $ext;
  
  // @var array<Name>  interfaces
  public $impl;
  
  // @var array<TraitUse>
  public $traits;
  
  // @var array<VarDecl|FnDecl>  members
  public $members;
  
  // @var MemberScope  class-scope
  public $scope;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param array    $mods
   * @param Ident    $id
   * @param array    $genc
   * @param Name     $ext
   * @param array    $impl
   * @param array    $traits
   * @param array    $members
   */
  public function __construct(Location $loc, array $mods = null, Ident $id, 
                              array $genc = null, Name $ext = null, 
                              array $impl = null, array $traits = null, 
                              array $members = null)
  {
    parent::__construct($loc);
    
    $this->mods = $mods;
    $this->id = $id;
    $this->genc = $genc;
    $this->ext = $ext;
    $this->impl = $impl;
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
    
    if ($this->ext)
      $this->ext = clone $this->ext;
    
    if ($this->impl) {
      $impl = $this->impl;
      $this->impl = [];
      
      foreach ($impl as $imp)
        $this->impl[] = clone $imp;  
    }
    
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
