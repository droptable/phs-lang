<?php

namespace phs\front\ast;

class UseUnpack extends Node
{
  public $base;
  public $items;
  
  public function __construct($base, $items)
  {
    $this->base = $base;
    $this->items = $items;
  }
}
