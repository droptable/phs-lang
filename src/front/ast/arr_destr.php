<?php

namespace phs\front\ast;

class ArrDestr extends Node 
{
  public $items;
  
  public function __construct($items)
  {
    $this->items = $items;
  }
}
