<?php

namespace phs\front\ast;

class TopexAttr extends Node
{
  public $attr;
  public $topex;
  
  public function __construct($attr, $topex)
  {
    $this->attr = $attr;
    $this->topex = $topex;
  }
}
