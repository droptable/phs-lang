<?php

namespace phs\front\ast;

class FnDecl extends Decl
{
  public $mods;
  public $id;
  public $params;
  public $body;
  
  // gets filled-in by the analyzer
  public $scope;  
  
  public function __construct($mods, $id, $params, $body)
  {
    $this->mods = $mods;
    $this->id = $id;
    $this->params = $params;
    $this->body = $body;
  }
}
