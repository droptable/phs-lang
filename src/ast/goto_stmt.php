<?php

namespace phs\ast;

class GotoStmt extends Node
{
  public $label;
  
  public function __construct($label)
  {
    $this->label = $label;
  }
}
