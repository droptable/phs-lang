<?php

namespace phs\front\ast;

class Ident extends Node
{
  public $data;
  
  // symbol lookup cache
  public $symbol;
  
  public function __construct($data)
  {
    $this->data = $data;
  }

  public function __clone()
  {
    if ($this->symbol)
      $this->symbol = clone $this->symbol;
    
    parent::__clone();
  }
}
