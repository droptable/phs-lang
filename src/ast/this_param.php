<?php

namespace phs\ast;

class ThisParam extends Node
{
  public $hint;
  public $id;
  public $init;
  public $ref;
  
  public function __construct($id, $hint, $init, $ref)
  {
    $this->id = $id;
    $this->hint = $hint;
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
