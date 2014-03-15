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
}
