<?php

namespace phs\ast;

use phs\Location;

class GotoStmt extends Stmt
{
  // @var Ident
  public $id;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Ident    $id
   */
  public function __construct(Location $loc, Ident $id)
  {
    parent::__construct($loc);
    $this->id = $id;
  }

  public function __clone()
  {
    $this->id = clone $this->id;
    
    parent::__clone();
  }
}
