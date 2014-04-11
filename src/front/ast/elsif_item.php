<?php

namespace phs\front\ast;

class ElsifItem extends Node
{
  public $expr;
  public $stmt;
  
  public function __construct($expr, $stmt)
  {
    $this->expr = $expr;
    $this->stmt = $stmt;
  }
}
