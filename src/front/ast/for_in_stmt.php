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

  public function __clone()
  {
    $this->lhs = clone $this->lhs;
    $this->rhs = clone $this->rhs;
    $this->stmt = clone $this->stmt;
    
    if ($this->scope)
      $this->scope = clone $this->scope;
    
    parent::__clone();
  }
}
