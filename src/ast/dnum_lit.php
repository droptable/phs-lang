<?php

namespace phs\ast;

use phs\Location;

class DNumLit extends Expr
{
  // @var float  number-value
  public $data;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param float    $data
   */
  public function __construct(Location $loc, $data)
  {
    parent::__construct($loc);
    $this->data = $data;
  }
}
