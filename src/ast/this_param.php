<?php

namespace phs\ast;

class ThisParam extends Node
{
  public $id;
  public $init;
  
  public function __construct($id, $init)
  {
    $this->id = $id;
    $this->init = $init;
  }
}
