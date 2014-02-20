<?php

namespace phs\ast;

class EngineConst extends Node
{
  public $type;
  
  public function __construct($type)
  {
    $this->type = $type;
  }
}
