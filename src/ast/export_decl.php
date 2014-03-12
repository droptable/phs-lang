<?php

namespace phs\ast;

class ExportDecl extends Node
{
  public $items;
  
  public function __construct($items)
  {
    $this->items = $items;
  }
}
