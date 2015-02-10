<?php

namespace phs\ast;

use phs\Location;

class TestStmt extends Stmt
{
  // @var StrLit
  public $name;
  
  // @var Block
  public $block;
  
  /**
   * constructor
   *
   * @param Location    $loc 
   * @param StrLit|null $name
   * @param Block       $block
   */
  public function __construct(Location $loc, StrLit $name = null, Block $block)
  {
    parent::__construct($loc);
    
    $this->name = $name;
    $this->block = $block;
  }

  public function __clone()
  {
    if ($this->name)
      $this->name = clone $this->name;
    
    $this->block = clone $this->block;
    
    parent::__clone();
  }
}
