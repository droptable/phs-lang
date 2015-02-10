<?php

namespace phs\ast;

use phs\Token;
use phs\Location;

class TypeId extends Expr
{
  public $tok;
  public $type;
  
  public function __construct(Location $loc, Token $tok)
  {
    parent::__construct($loc);
    $this->tok = $tok;
    $this->type = $tok->type;
  }
}
