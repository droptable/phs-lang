<?php

namespace phs\ast;

class ClassDecl extends Decl
{
  public $mods;
  public $id;
  public $genc;
  public $ext;
  public $impl;
  public $traits;
  public $members;
  public $incomp;
  
  // class-scope
  public $scope;
  
  public function __construct($mods, $id, $genc, $ext, $impl, $traits, $members, $incomp = false)
  {
    $this->mods = $mods;
    $this->id = $id;
    $this->genc = $genc;
    $this->ext = $ext;
    $this->impl = $impl;
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
