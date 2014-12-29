<?php

namespace phs\ast;

// unused in parser v2

use phs\Location;

class KStrLit extends Expr
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
    $this->data = (string) $data;
  }

  public function __clone()
  {
    parent::__clone();
  }
}
