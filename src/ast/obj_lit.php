<?php

namespace phs\ast;

class ObjLit extends Node
{
  public $pairs;
  
  public function __construct($pairs)
  {
    $this->pairs = $pairs;
  }
}
