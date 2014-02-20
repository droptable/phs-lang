<?php

namespace phs\ast;

class ElseItem extends Node
{
  public $stmt;
  
  public function __construct($stmt)
  {
    $this->stmt = $stmt;
  }
}
