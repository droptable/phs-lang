<?php

namespace phs\ast;

class IfaceDecl extends Node
{
  public $id;
  public $exts;
  public $members;
  
  public function __construct($id, $exts, $mmebers)
  {
    $this->id = $id;
    $this->exts = $exts;
    $this->members = $members;
  }
}
