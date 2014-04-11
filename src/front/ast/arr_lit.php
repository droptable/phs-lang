<?php

namespace phs\front\ast;

class ArrLit extends Expr
{
  public $items;
  
  public function __construct($items)
  {
    $this->items = $items;
  }
}
