<?php

namespace phs\ast;

class AttrDecl extends Node
{
  public $attr;
  
  public function __construct($attr)
  {
    assert($attr instanceof AttrDef ||
           $attr instanceof CompAttr ||
           $attr instanceof TopexAttr);
    
    $this->attr = $attr;
  }
}
