<?php

namespace phs\front\ast;

class FnExpr extends Expr
{
  public $id;
  public $params;
  public $body;
  
  // @var Scope
  public $scope;  
  
  // @var FnSymbol  only defined if `id` is not null
  public $symbol;
  
  public function __construct($id, $params, $body)
  {
    $this->id = $id;
    $this->params = $params;
    $this->body = $body;
  }
}
