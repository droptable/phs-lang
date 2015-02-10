<?php

namespace phs\ast;

use phs\Location;

class ThisParam extends Node
{
  // @var Ident
  public $id;
  
  // @var Expr
  public $init;
  
  // @var bool
  public $ref;
  
  /**
   * constructor
   *
   * @param Location  $loc
   * @param bool      $ref
   * @param Ident     $id 
   * @param Expr|null $init
   */
  public function __construct(Location $loc, $ref, Ident $id, Expr $init = null)
  {
    parent::__construct($loc);
    
    $this->id = $id;
    $this->init = $init;
    $this->ref = $ref;
  }

  public function __clone()
  {
    if ($this->hint)
      $this->hint = clone $this->hint;
    
    $this->id = clone $this->id;
    
    if ($this->init)
      $this->init = clone $this->init;
    
    parent::__clone();
  }
}
