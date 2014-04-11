<?php

namespace phs\front\ast;

class TraitDecl extends Decl
{
  public $id;
  public $members;
  
  public function __construct($id, $members)
  {
    $this->id = $id;
    $this->members = $members;
  }
}
