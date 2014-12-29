<?php

namespace phs\ast;

use phs\Location;

class Ident extends Node
{
  // @var string
  public $data;
  
  // @var Symbol
  public $sym;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param string   $data
   */
  public function __construct(Location $loc, $data)
  {
    parent::__construct($loc);
    $this->data = $data;
  }

  public function __clone()
  {
    if ($this->symbol)
      $this->symbol = clone $this->symbol;
    
    parent::__clone();
  }
}
