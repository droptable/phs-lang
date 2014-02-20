<?php

namespace phs\ast;

class VarDecl extends Node
{
  public $mods;
  public $vars;
  
  public function __construct($mods, $vars)
  {
    $this->mods = $mods;
    $this->vars = $vars;
  }
}
