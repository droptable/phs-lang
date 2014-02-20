<?php

namespace phs\ast;

class PhpUse extends Node
{
  public $items;
  
  public function __construct($items)
  {
    $this->items = $items;
  }
}
