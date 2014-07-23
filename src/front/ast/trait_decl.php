<?php

namespace phs\front\ast;

class TraitDecl extends Decl
{
  public $mods;
  public $id;
  public $members;
  
  public function __construct($mods, $id, $traits, $members)
  {
    $this->mods = $mods;
    $this->id = $id;
    $this->traits = $traits;
    $this->members = $members;
  }
}
