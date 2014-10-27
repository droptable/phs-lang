<?php

namespace phs\ast;

class CtorDecl extends Decl
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

  public function __clone()
  {
    if ($this->mods) {
      $mods = $this->mods;
      $this->mods = [];
      
      foreach ($mods as $mod)
        $this->mods[] = clone $mod;  
    }
    
    if ($this->params) {
      $params = $this->params;
      $this->params = [];
      
      foreach ($params as $param)
        $this->params[] = clone $param;      
    }
    
    if ($this->body)
      $this->body = clone $this->body;
    
    parent::__clone();
  }
}
