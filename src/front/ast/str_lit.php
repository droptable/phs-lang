<?php

namespace phs\front\ast;

class StrLit extends Expr
{
  public $data;
  public $flag;
  
  public function __construct($data, $flag)
  {
    $this->data = (string)$data;
    $this->flag = $flag;
  }
}
