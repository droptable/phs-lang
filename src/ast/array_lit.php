<?php

namespace phs\ast;

class ArrayLit extends Node
{
  public $items;
  
  public function __construct($items)
  {
    $this->items = $items;
  }
}
