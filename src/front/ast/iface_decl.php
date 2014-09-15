<?php

namespace phs\front\ast;

class IfaceDecl extends Decl
{
  public $mods;
  public $id;
  public $impl;
  public $members;
  public $incomp;
  
  public function __construct($mods, $id, $impl, $members, $incomp = false)
  {
    $this->mods = $mods;
    $this->id = $id;
    $this->impl = $impl;
    $this->members = $members;
    $this->incomp = $incomp;
  }
}
