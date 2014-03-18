<?php

namespace phs\ast;

class UseDecl extends Decl
{
  public $item;
  
  public function __construct($item)
  {
    assert ($item instanceof Name ||
            $item instanceof UseAlias ||
            $item instanceof UseUnpack);
    
    $this->item = $item;
  }
}
