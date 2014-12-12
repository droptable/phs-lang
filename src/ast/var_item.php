<?php

namespace phs\ast;

class VarItem extends Node
{
  public $id;
  public $init;
  public $hint;
  public $ref;
  
  // @var Symbol
  public $symbol;
  
  public function __construct($id, $hint, $init, $ref)
  {
    $this->id = $id;
    $this->hint = $hint;
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
