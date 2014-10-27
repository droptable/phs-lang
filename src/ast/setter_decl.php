<?php

namespace phs\ast;

class SetterDecl extends Decl
{
  public $id;
  public $params;
  public $body;
  
  public function __construct($id, $params, $body)
  {
    $this->id = $id;
    $this->params = $params;
    $this->body = $body;
  }

  public function __clone()
  {
    $this->id = clone $this->id;
    
    if ($this->params) {
      $params = $this->params;
      $this->params = [];
      
      foreach ($params as $param)
        $this->params[] = clone $param;      
    }
    
    $this->body = clone $this->body;
    
    parent::__clone();
  }
}
