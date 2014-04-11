<?php

namespace phs\front\ast;

class ObjDestr extends Node 
{
  public $items;
  
  public function __construct($items)
  {
    $this->items = $items;
  }
}
