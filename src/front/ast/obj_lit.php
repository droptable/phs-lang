<?php

namespace phs\front\ast;

class ObjLit extends Expr
{
  public $pairs;
  
  public function __construct($pairs)
  {
    $this->pairs = $pairs;
  }
}
