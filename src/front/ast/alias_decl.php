<?php

namespace phs\front\ast;

class AliasDecl extends Decl
{
  public $id;
  
  public function __construct($id, $orig)
  {
    $this->id = $id;
    $this->orig = $orig;
  }
}
