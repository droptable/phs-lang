<?php

namespace phs\ast;

class ObjectLit extends Node
{
  public $pairs;
  
  public function __construct($pairs)
  {
    $this->pairs = $pairs;
  }
}
