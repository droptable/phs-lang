<?php

namespace phs\ast;

class DelExpr extends Node
{
  public $id;
  
  public function __construct($id)
  {
    $this->id = $id;
  }
}
