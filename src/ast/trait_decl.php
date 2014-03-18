<?php

namespace phs\ast;

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
