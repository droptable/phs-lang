<?php

namespace phs\ast;

use phs\Location;

class UseAlias extends Node
{
  // @var Name
  public $name;
  
  // @var Ident
  public $alias;
  
  /**
   * constructor
   *
   * @param Location $loc 
   * @param Name     $name
   * @param Ident    $alias
   */
  public function __construct(Location $loc, Name $name, Ident $alias)
  {
    parent::__construct($loc);
    
    $this->name = $name;
    $this->alias = $alias;
  }

  public function __clone()
  {
    $this->name = clone $this->name;
    $this->alias = clone $this->alias;
    
    parent::__clone();
  }
}
