<?php

namespace phs\front\ast;

class IfaceDecl extends Decl
{
  public $id;
  public $exts;
  public $members;
  
  public function __construct($id, $exts, $members)
  {
    $this->id = $id;
    $this->exts = $exts;
    $this->members = $members;
  }
}
