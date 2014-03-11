<?php

namespace phs\ast;

class BreakStmt extends Node
{
  public $id;
  
  public function __construct($id)
  {
    $this->id = $id;
  }
}
