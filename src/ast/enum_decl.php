<?php

namespace phs\ast;

class EnumDecl extends Decl
{
  public $mods;
  public $vars;
  
  public function __construct($mods, $vars)
  {
    throw new \Exception('TODO: implement enums');
    
    $this->mods = $mods;
    $this->vars = $vars;
  }

  public function __clone()
  {
    if ($this->mods) {
      $mods = $this->mods;
      $this->mods = [];
      
      foreach ($mods as $mod)
        $this->mods[] = clone $mod;  
    }
    
    $vars = $this->vars;
    $this->vars = [];
    
    foreach ($vars as $var)
      $this->vars[] = clone $var;
    
    parent::__clone();
  }
}
