<?php

namespace phs\front\ast;

class KStrLit extends Expr
{  
  public function __construct($data)
  {
    $this->data = (string) $data;
  }

  public function __clone()
  {
    parent::__clone();
  }
}
