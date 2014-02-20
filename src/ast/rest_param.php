<?php

namespace phs\ast;

class RestParam extends Node
{
  public $id;
  
  public function __construct($id)
  {
    $this->id = $id;
  }
}
