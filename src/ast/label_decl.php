<?php

namespace phs\ast;

use phs\Location;

class LabelDecl extends Decl
{
  // @var Ident
  public $id;
  
  // @var Stmt
  public $stmt;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Ident    $id
   * @param Stmt     $stmt
   */
  public function __construct(Location $loc, Ident $id, Stmt $stmt)
  {
    parent::__construct($loc);
    
    $this->id = $id;
    $this->stmt = $stmt;
  }

  public function __clone()
  {
    $this->id = clone $this->id;
    $this->stmt = clone $this->stmt;
    
    parent::__clone();
  }
}
