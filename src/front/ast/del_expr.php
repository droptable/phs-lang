<?php

namespace phs\front\ast;

class DelExpr extends Expr
{
  public $id;
  
  public function __construct($id)
  {
    $this->id = $id;
  }
}
