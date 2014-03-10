<?php

namespace phs\ast;

class Label extends Node
{
  public $id;
  public $stmt;
  
  public function __construct($label, $stmt)
  {
    $this->label = $label;
    $this->stmt = $stmt;
  }
}
