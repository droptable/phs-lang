<?php

namespace phs\front\ast;

class RestParam extends Node
{
  public $id;
  public $hint;
  
  public function __construct($hint, $id)
  {
    $this->hint = $hint;
    $this->id = $id;
  }
}
