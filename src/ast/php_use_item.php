<?php

namespace phs\ast;

// unused in parser v2
use phs\Location;

class PhpUseItem extends Node
{
  // @var Ident
  public $id;
  
  // @var Ident
  public $alias;
  
  /**
   * constructor
   *
   * @param Location   $loc
   * @param Ident      $id
   * @param Ident|null $alias
   */
  public function __construct(Location $loc, Ident $id, Ident $alias = null)
  {
    parent::__construct($loc);
    
    $this->id = $id;
    $this->alias = $alias;
  }

  public function __clone()
  {
    $this->id = clone $this->id;
    
    if ($this->alias)
      $this->alias = clone $this->alias;
    
    parent::__clone();
  }
}
