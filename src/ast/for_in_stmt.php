<?php

namespace phs\ast;

use phs\Scope;
use phs\Location;

class ForInStmt extends Stmt
{
  // @var Expr  left-hand-side
  public $lhs;
  
  // @var Expr  right-hand-side
  public $rhs;
  
  // @var Stmt  inner statement
  public $stmt;
  
  // @var Scope  own scope
  public $scope;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Expr     $lhs
   * @param Expr     $rhs
   * @param Stmt     $stmt
   */
  public function __construct(Location $loc, Expr $lhs, Expr $rhs, Stmt $stmt)
  {
    parent::__construct($loc);
    
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
