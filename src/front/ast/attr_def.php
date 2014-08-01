<?php

namespace phs\front\ast;

class AttrDef extends Node
{
  public $items;
  
  public function __construct($items)
  {
    $this->items = $items;
  }
}
