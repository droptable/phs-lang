<?php

namespace phs\front\ast;

class FnExpr extends Expr
{
  public $id;
  public $params;
  public $body;
  
  // gets filled-in by the analyzer
  public $scope;
  
  public function __construct($id, $params, $body)
  {
    $this->id = $id;
    $this->params = $params;
    $this->body = $body;
  }
}
