<?php

namespace phs\ast;

class BreakStmt extends Node
{
  public $label;
  
  public function __construct($label)
  {
    $this->label = $label;
  }
}
