<?php

namespace phs\front\ast;

class ElseItem extends Node
{
  public $stmt;
  
  public function __construct($stmt)
  {
    $this->stmt = $stmt;
  }
}
