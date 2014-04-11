<?php

namespace phs\front\ast;

class LabelDecl extends Decl
{
  public $id;
  public $comp;
  
  public function __construct($id, $comp)
  {
    $this->id = $id;
    $this->comp = $comp;
  }
}
