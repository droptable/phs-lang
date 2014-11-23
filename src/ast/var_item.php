<?php

namespace phs\ast;

class VarItem extends Node
{
  public $id;
  public $init;
  public $ref;
  
  // @var Symbol
  public $symbol;
  
  public function __construct($id, $init, $ref)
  {
    $this->id = $id;
    $this->init = $init;
    $this->ref = $ref;
  }

  public function __clone()
  {
    $this->id = clone $this->id;
    
    if ($this->init)
      $this->init = clone $this->init;
    
    parent::__clone();
  }
}
