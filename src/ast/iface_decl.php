<?php

namespace phs\ast;

class IfaceDecl extends Decl
{
  public $mods;
  public $id;
  public $impl;
  public $members;
  public $incomp;
  
  // @var Scope  member-scope
  public $scope;
  
  public function __construct($mods, $id, $impl, $members, $incomp = false)
  {
    $this->mods = $mods;
    $this->id = $id;
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
