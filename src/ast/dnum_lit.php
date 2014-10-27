<?php

namespace phs\ast;

class DNumLit extends Expr
{
  public $data;
  
  public function __construct($data)
  {
    $this->data = $data;
  }
}
