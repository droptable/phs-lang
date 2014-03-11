<?php

namespace phs\ast;

class ContinueStmt extends Node
{
  public $id;
  
  public function __construct($id)
  {
    $this->id = $id;
  }
}
