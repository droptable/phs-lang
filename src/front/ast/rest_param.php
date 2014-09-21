<?php

namespace phs\front\ast;

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
}
