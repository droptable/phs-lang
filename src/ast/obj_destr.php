<?php

namespace phs\ast;

class ObjDestr extends Node 
{
  public $items;
  
  public function __construct($items)
  {
    $this->items = $items;
  }
}
