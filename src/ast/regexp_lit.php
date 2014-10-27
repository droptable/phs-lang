<?php

namespace phs\ast;

class RegexpLit extends Expr
{
  public $data;
  
  public function __construct($data)
  {
    $this->data = $data;
  }
}
