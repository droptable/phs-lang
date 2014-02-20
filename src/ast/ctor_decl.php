<?php

namespace phs\ast;

class CtorDecl extends Node
{
  public $mods;
  public $params;
  public $body;
  
  public function __construct($mods, $params, $body)
  {
    $this->mods = $mods;
    $this->params = $params;
    $this->body = $body;
  }
}
