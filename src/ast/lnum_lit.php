<?php

namespace phs\ast;

use phs\Location;

class LNumLit extends Expr
{
  // @var int  value
  public $data;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param int      $data
   */
  public function __construct(Location $loc, $data)
  {
    parent::__construct($loc);
    $this->data = $data;
  }

  public function __clone()
  {
    parent::__clone();
  }
}
