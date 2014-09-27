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

  public function __clone()
  {
    $this->id = clone $this->id;
    
    if ($this->mods) {
      $mods = $this->mods;
      $this->mods = [];
      
      foreach ($mods as $mod)
        $this->mods[] = clone $mod;  
    }
    
    if ($this->alias)
      $this->alias = clone $this->alias;
    
    parent::__clone();
  }
}
