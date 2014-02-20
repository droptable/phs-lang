<?php

namespace phs\ast;

class ClassDecl extends Node
{
  public $mods;
  public $id;
  public $ext;
  public $impl;
  public $members;
  
  public function __construct($mods, $id, $ext, $impl, $members)
  {
    $this->mods = $mods;
    $this->id = $id;
    $this->ext = $ext;
    $this->impl = $impl;
    $this->members = $members;
  }
}
