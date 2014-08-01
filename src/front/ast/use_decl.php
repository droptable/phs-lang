<?php

namespace phs\front\ast;

class UseDecl extends Decl
{
  public $item;
  
  public function __construct($item)
  {
    $this->item = $item;
  }
}
