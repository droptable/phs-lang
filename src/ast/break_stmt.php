<?php

namespace phs\ast;

use phs\Location;

class BreakStmt extends Stmt
{
  // @var Ident  label
  public $id;
  
  // @var int  level to break (gets resolved later)
  public $level;
  
  /**
   * constructor
   *
   * @param Location   $loc
   * @param Ident|null $id 
   */
  public function __construct(Location $loc, Ident $id = null)
  {
    parent::__construct($loc);
    $this->id = $id;
  }
  
  public function __clone()
  {
    if ($this->id)
      $this->id = clone $this->id;
    
    parent::__clone();
  }
}
