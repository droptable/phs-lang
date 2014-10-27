<?php

namespace phs\ast;

class TraitDecl extends Decl
{
  public $mods;
  public $id;
  public $traits;
  public $members;
  public $incomp;
  
  // @var Scope  member-scope
  public $scope;
  
  public function __construct($mods, $id, $traits, $members, $incomp = false)
  {
    $this->mods = $mods;
    $this->id = $id;
    $this->traits = $traits;
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
