<?php

namespace phs\front\ast;

abstract class Decl extends Node
{
  // the computed symbol for this declaration
  public $symbol;

  public function __clone()
  {
    $this->symbol = null;
    
    parent::__clone();
  }
}
