<?php

namespace phs\ast;

class Param extends Node
{
  public $ref;
  public $mods;
  public $hint;
  public $id;
  public $init;
  public $opt;
  
  // @var ParamSymbol
  public $symbol;
  
  public function __construct($ref, $mods, $id, $hint, $init, $opt)
  {
    $this->ref = $ref;
    $this->mods = $mods;
    $this->hint = $hint;
    $this->id = $id;
    $this->init = $init;
    $this->opt = $opt;
  }

  public function __clone()
  {
    if ($this->mods) {
      $mods = $this->mods;
      $this->mods = [];
      
      foreach ($mods as $mod)
        $this->mods[] = clone $mod;  
    }
    
    if ($this->hint)
      $this->hint = clone $this->hint;
    
    $this->id = clone $this->id;
    
    if ($this->init)
      $this->init = clone $this->init;
    
    $this->symbol = null;
    
    parent::__clone();
  }
}
