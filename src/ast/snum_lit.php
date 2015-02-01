<?php

namespace phs\ast;

// currently unused

use phs\Location;

class SNumLit extends Expr
{
  // @var int
  public $data;
  
  // @var string
  public $suffix;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param int      $data
   * @param string   $suffix
   */
  public function __construct(Location $loc, $data, $suffix)
  {
    parent::__construct($loc);
    
    $this->data = $data;
    $this->suffix = $suffix;
  }
}
