<?php

namespace phs\ast;

class CheckExpr extends Expr
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
