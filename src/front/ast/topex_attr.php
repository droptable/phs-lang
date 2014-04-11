<?php

namespace phs\front\ast;

class TopexAttr extends Node
{
  public $def;
  public $topex;
  
  public function __construct($def, $topex)
  {
    $this->def = $def;
    $this->topex = $topex;
  }
}
