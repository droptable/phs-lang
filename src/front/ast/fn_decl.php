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
  
  public function __construct($mods, $id, $params, $body)
  {
    $this->mods = $mods;
    $this->id = $id;
    $this->params = $params;
    $this->body = $body;
  }

  public function __clone()
  {
    if ($this->mods) {
      $mods = $this->mods;
      $this->mods = [];
      
      foreach ($mods as $mod)
        $this->mods[] = clone $mod;  
    }
    
    $this->id = clone $this->id;
    
    if ($this->params) {
      $params = $this->params;
      $this->params = [];
      
      foreach ($params as $param)
        $this->params[] = clone $param;      
    }
    
    if ($this->body)
      $this->body = clone $this->body;
    
    if ($this->scope)
      $this->scope = clone $this->scope;
    
    parent::__clone();
  }
}
