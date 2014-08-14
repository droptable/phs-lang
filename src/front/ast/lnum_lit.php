<?php

namespace phs\front\ast;

class LNumLit extends Expr
{
  public $data;
  
  public function __construct($data)
  {
    $this->data = $data;
  }
}
