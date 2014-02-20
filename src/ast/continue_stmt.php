<?php

namespace phs\ast;

class ContinueStmt extends Node
{
  public $label;
  
  public function __construct($label)
  {
    $this->label = $label;
  }
}
