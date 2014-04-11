<?php

namespace phs\front\ast;

class AttrDecl extends Decl
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
