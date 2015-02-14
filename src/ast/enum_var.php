<?php

namespace phs\ast;

use phs\Location;

class EnumItem extends Node
{
  public $id;
  public $init;
  
  /**
   * constructor
   *
   * @param Location  $loc
   * @param Ident     $id  
   * @param Expr|null $init
   */
  public function __construct(Location $loc, Ident $id, Expr $init = null)
  {
    parent::__construct($loc);
    
    $this->id = $id;
    $this->init = $init;
  }

  public function __clone()
  {
    $this->id = clone $this->id;
    
    if ($this->init)
      $this->init = clone $this->init;
    
    parent::__clone();
  }
}
