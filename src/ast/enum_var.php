<?php

namespace phs\ast;

class EnumVar extends Node
{
  public $id;
  public $init;
  
  public function __construct($id, $init)
  {
    $this->id = $id;
    $this->init = $init;
  }

  public function __clone()
  {
    $this->id = clone $this->id;
    
    if ($this->init)
      $this->init = clone $this->init;
    
    parent::__clone();
  }
}
