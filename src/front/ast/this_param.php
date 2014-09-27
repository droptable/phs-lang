<?php

namespace phs\front\ast;

class ThisParam extends Node
{
  public $hint;
  public $id;
  public $init;
  public $ref;
  
  public function __construct($hint, $id, $init, $ref)
  {
    $this->hint = $hint;
    $this->id = $id;
    $this->init = $init;
    $this->ref = $ref;
  }

  public function __clone()
  {
    if ($this->hint)
      $this->hint = clone $this->hint;
    
    $this->id = clone $this->id;
    
    if ($this->init)
      $this->init = clone $this->init;
    
    parent::__clone();
  }
}
