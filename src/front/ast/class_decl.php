<?php

namespace phs\front\ast;

class ClassDecl extends Decl
{
  public $mods;
  public $id;
  public $ext;
  public $impl;
  public $traits;
  public $members;
  public $incomp;
  
  // class-scope
  public $scope;
  
  public function __construct($mods, $id, $ext, $impl, $traits, $members, $incomp = false)
  {
    $this->mods = $mods;
    $this->id = $id;
    $this->ext = $ext;
    $this->impl = $impl;
    $this->traits = $traits;
    $this->members = $members;
    $this->incomp = $incomp;
  }
}
