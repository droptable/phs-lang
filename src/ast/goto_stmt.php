<?php

namespace phs\ast;

class GotoStmt extends Node
{
  public $id;
  
  public function __construct($id)
  {
    $this->id = $id;
  }
}
