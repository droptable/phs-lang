<?php

namespace phs\ast;

use phs\Location;

class FinallyItem extends Node
{
  // @var Block
  public $body;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Block    $body
   */
  public function __construct(Location $loc, Block $body)
  {
    parent::__construct($loc);
    $this->body = $body;
  }

  public function __clone()
  {
    $this->body = clone $this->body;
    
    parent::__clone();
  }
}
