<?php

namespace phs\ast;

class ArrLit extends Node
{
  public $items;
  
  public function __construct($items)
  {
    $this->items = $items;
  }
}
