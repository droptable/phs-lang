<?php

namespace phs\front\ast;

class AssignRefExpr extends Expr
{
  public $left;
  public $right;
  
  public function __construct($left, $right)
  {
    $this->left = $left;
    $this->right = $right;
  }
}
