<?php

namespace phs\ast;

class ObjLit extends Expr
{
  public $pairs;
  
  public function __construct($pairs)
  {
    $this->pairs = $pairs;
  }
}
