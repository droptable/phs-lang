<?php

namespace phs\ast;

use phs\Location;

class EngineConst extends Expr
{
  // @var int  token-type
  public $type;
  
  // @var Symbol  if bound to a symbol
  public $symbol;
  
  /**
   * construct
   *
   * @param Location $loc
   * @param int      $type
   */
  public function __construct(Location $loc, $type)
  {
    parent::__construct($loc);
    $this->type = $type;
  }
}
