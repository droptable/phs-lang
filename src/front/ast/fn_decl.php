<?php

namespace phs\front\ast;

class FnDecl extends Decl
{
  public $mods;
  public $id;
  public $params;
  public $body;
  
  // @var Scope
  public $scope;  
  
  // @var FnSymbol
  public $symbol;
  
  public function __construct($mods, $id, $params, $body)
  {
    $this->mods = $mods;
    $this->id = $id;
    $this->params = $params;
    $this->body = $body;
  }
}
