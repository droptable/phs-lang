<?php

namespace phs\front\ast;

class TraitItem extends Node
{
  public $id;
  public $mods;
  public $alias;
  
  public function __construct($id, $mods, $alias)
  {
    $this->id = $id;
    $this->mods = $mods;
    $this->alias = $alias;
  }
}
