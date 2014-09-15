<?php

namespace phs\front\ast;

class RestParam extends Node
{
  public $id;
  public $hint;
  
  // @var ParamSymbol
  public $symbol;
  
  public function __construct($hint, $id)
  {
    $this->hint = $hint;
    $this->id = $id;
  }
}
