<?php

namespace phs\ast;

class FnExpr extends Expr
{
  public $id;
  public $params;
  public $body;
  
  // @var Scope
  public $scope;
  
  // @var Symbol  only if `id` is not null
  public $symbol;
  
  public function __construct($id, $params, $body)
  {
    $this->id = $id;
    $this->params = $params;
    $this->body = $body;
  }

  public function __clone()
  {
    if ($this->id)
      $this->id = clone $this->id;
    
    $this->body = clone $this->body;
    
    if ($this->params) {
      $params = $this->params;
      $this->params = [];
      
      foreach ($params as $param)
        $this->params[] = clone $param;      
    }
    
    if ($this->scope) 
      $this->scope = clone $this->scope;
    
    // not done here
    $this->symbol = null;
    
    parent::__clone();
  }
}
