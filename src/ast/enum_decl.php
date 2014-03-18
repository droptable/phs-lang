<?php

namespace phs\ast;

class EnumDecl extends Decl
{
  public $mods;
  public $members;
  
  public function __construct($mods, $members)
  {
    $this->mods = $mods;
    $this->members = $members;
  }
}
