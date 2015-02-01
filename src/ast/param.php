<?php

namespace phs\ast;

use phs\Token;
use phs\Symbol;
use phs\Location;

class Param extends Node
{
  // @var bool  pass-by-reference
  public $ref;
  
  // @var array<Token>  modifiers
  public $mods;
  
  // @var TypeName  
  public $hint;
  
  // @var Ident
  public $id;
  
  // @var Expr  default-initializer
  public $init;
  
  // @var bool  optional
  public $opt;
  
  // @var Symbol
  public $symbol;
  
  /**
   * constructor
   *
   * @param Location  $loc
   * @param bool      $ref
   * @param array     $mods
   * @param Ident     $id 
   * @param TypeName  $hint
   * @param Expr|null $init
   * @param bool      $opt
   */
  public function __construct(Location $loc, $ref, $mods, Ident $id, 
                              TypeName $hint, Expr $init = null, 
                              $opt = false)
  {
    parent::__construct($loc);
    
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
