<?php

namespace phs\ast;

use phs\Location;

class RegexpLit extends Expr
{
  // @var string
  public $data;
  
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
}
