<?php

namespace phs\ast;

use phs\Location;

class VarDecl extends Decl
{
  // @var array<Token>
  public $mods;
  
  // @var array<VarItem>
  public $vars;
  
  /**
   * constructor
   *
   * @param Location       $loc
   * @param array|null     $mods
   * @param array<VarItem> $vars
   */
  public function __construct(Location $loc, array $mods = null, array $vars)
  {
    parent::__construct($loc);
    
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
