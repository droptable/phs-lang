<?php

namespace phs\front\ast;

class DtorDecl extends Decl
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
