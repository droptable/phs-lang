<?php

namespace phs\ast;

class EnumDecl
{
  public $mods;
  public $vars;
  
  public function __construct($mods, $vars)
  {
    $this->mods = $mods;
    $this->vars = $vars;
  }
}
