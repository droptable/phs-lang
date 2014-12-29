<?php

namespace phs\ast;

use phs\Symbol;
use phs\Location;

abstract class Decl extends Node
{
  // @var Symbol  the computed symbol for this declaration
  public $sym;
  
  public function __clone()
  {
    $this->sym = null;
    
    parent::__clone();
  }
}
