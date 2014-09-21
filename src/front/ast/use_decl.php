<?php

namespace phs\front\ast;

class UseDecl extends Decl
{
  public $pub;
  public $item;
  
  public function __construct($item, $pub)
  {
    $this->item = $item;
    $this->pub = $pub;
  }
}
