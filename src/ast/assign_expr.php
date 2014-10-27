<?php

namespace phs\ast;

class AssignExpr extends Expr
{
  public $left;
  public $op;
  public $right;
  
  public function __construct($left, $op, $right)
  {
    $this->left = $left;
    $this->op = $op;
    $this->right = $right;
  }
  
  public function __clone()
  {
    $this->left = clone $this->left;
    $this->op = clone $this->op;
    $this->right = clone $this->right;
    
    parent::__clone();
  }
}
