<?php

namespace phs\front\ast;

class ThisParam extends Node
{
  public $hint;
  public $id;
  public $init;
  
  public function __construct($hint, $id, $init)
  {
    $this->hint = $hint;
    $this->id = $id;
    $this->init = $init;
  }
}
