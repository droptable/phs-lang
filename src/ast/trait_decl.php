<?php

namespace phs\ast;

class TraitDecl extends Node
{
  public $id;
  public $members;
  
  public function __construct($id, $members)
  {
    $this->id = $id;
    $this->members = $members;
  }
}
