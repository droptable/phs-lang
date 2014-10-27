<?php

namespace phs\ast;

class ElseItem extends Node
{
  public $stmt;
  
  public function __construct($stmt)
  {
    $this->stmt = $stmt;
  }

  public function __clone()
  {
    $this->stmt = clone $this->stmt;
    
    parent::__clone();
  }
}
