<?php

namespace phs\front\ast;

class IfaceDecl extends Decl
{
  public $mods;
  public $id;
  public $exts;
  public $members;
  
  public function __construct($mods, $id, $exts, $members)
  {
    $this->mods = $mods;
    $this->id = $id;
    $this->exts = $exts;
    $this->members = $members;
  }
}
