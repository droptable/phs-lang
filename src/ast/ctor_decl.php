<?php

namespace phs\ast;

use phs\Location;

class CtorDecl extends Decl
{
  // @var array<Token>  modifiers
  public $mods;
  
  // @var array<ParamDecl>  parameters
  public $params;
  
  // @var Block  inner statements
  public $body;
  
  /**
   * constructor
   *
   * @param Location   $loc
   * @param array      $mods
   * @param array      $params
   * @param Block|null $body
   */
  public function __construct(Location $loc, array $mods, 
                              array $params, Block $body = null)
  {
    parent::__construct($loc);
    
    $this->mods = $mods;
    $this->params = $params;
    $this->body = $body;
  }

  public function __clone()
  {
    if ($this->mods) {
      $mods = $this->mods;
      $this->mods = [];
      
      foreach ($mods as $mod)
        $this->mods[] = clone $mod;  
    }
    
    if ($this->params) {
      $params = $this->params;
      $this->params = [];
      
      foreach ($params as $param)
        $this->params[] = clone $param;      
    }
    
    if ($this->body)
      $this->body = clone $this->body;
    
    parent::__clone();
  }
}
