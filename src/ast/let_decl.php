<?php

namespace phs\ast;

class LetDecl extends Decl
{
  public $mods;
  public $vars;
  
  public function __construct($mods, $vars)
  {
    $this->mods = $mods;
    $this->vars = $vars;
  }
}
