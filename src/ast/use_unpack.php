<?php

namespace phs\ast;

class UseUnpack
{
  public $base;
  public $items;
  
  public function __construct($base, $items)
  {
    $this->base = $base;
    $this->items = $items;
  }
}
