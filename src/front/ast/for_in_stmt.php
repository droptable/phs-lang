<?php

namespace phs\front\ast;

class ForInStmt extends Stmt
{
  public $lhs;
  public $rhs;
  public $stmt;
  
  // @var Scope  own scope
  public $scope;
  
  public function __construct($lhs, $rhs, $stmt)
  {
    $this->lhs = $lhs;
    $this->rhs = $rhs;
    $this->stmt = $stmt;
  }
}
