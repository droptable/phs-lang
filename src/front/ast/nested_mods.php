<?php

namespace phs\front\ast;

class NestedMods extends Node
{
  public $mods;
  public $members;
  
  public function __construct($mods, $members)
  {
    $this->mods = $mods;
    $this->members = $members;
  }
}
