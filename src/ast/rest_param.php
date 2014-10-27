<?php

namespace phs\ast;

class RestParam extends Node
{
  public $id;
  public $ref;
  public $hint;
  
  // @var ParamSymbol
  public $symbol;
  
  public function __construct($hint, $id, $ref)
  {
    $this->hint = $hint;
    $this->id = $id;
    $this->ref = $ref;
  }

  public function __clone()
  {
    $this->id = clone $this->id;
    
    if ($this->hint)
      $this->hint = clone $this->hint;
    
    $this->symbol = null;
    
    parent::__clone();
  }
}
