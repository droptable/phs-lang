<?php

namespace phs\front\ast;

class TraitDecl extends Decl
{
  public $mods;
  public $id;
  public $members;
  
  public function __construct($mods, $id, $members)
  {
    $this->mods = $mods;
    $this->id = $id;
    $this->members = $members;
  }
}
