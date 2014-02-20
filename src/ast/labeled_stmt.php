<?php

namespace phs\ast;

class LabeledStmt extends Node
{
  public $label;
  public $stmt;
  
  public function __construct($label, $stmt)
  {
    $this->label = $label;
    $this->stmt = $stmt;
  }
}
