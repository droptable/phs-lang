<?php

namespace phs\ast;

class TypeId extends Expr
{
  public $type;
  
  public function __construct($type)
  {
    $this->type = $type;
  }
}
