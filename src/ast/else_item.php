<?php

namespace phs\ast;

use phs\Location;

class ElseItem extends Node
{
  // @var Stmt
  public $stmt;
  
  /**
   * constructor 
   *
   * @param Location $loc
   * @param Stmt     $stmt
   */
  public function __construct(Location $loc, Stmt $stmt)
  {
    parent::__construct($loc);
    $this->stmt = $stmt;
  }

  public function __clone()
  {
    $this->stmt = clone $this->stmt;
    
    parent::__clone();
  }
}
