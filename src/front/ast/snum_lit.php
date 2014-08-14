<?php

namespace phs\front\ast;

class SNumLit extends Expr
{
  public $data;
  public $suffix;
  
  public function __construct($data, $suffix)
  {
    $this->data = $data;
    $this->suffix = $suffix;
  }
}
