<?php

namespace phs\ast;

class RestParam extends Node
{
  public $id;
  public $mods;
  
  public function __construct($mods, $id)
  {
    $this->mods = $mods;
    $this->id = $id;
  }
}
