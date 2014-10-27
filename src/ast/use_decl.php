<?php

namespace phs\ast;

class UseDecl extends Decl
{
  public $pub;
  public $item;
  
  public function __construct($item, $pub)
  {
    $this->item = $item;
    $this->pub = $pub;
  }

  public function __clone()
  {
    $this->item = clone $this->item;
    
    parent::__clone();
  }
}
