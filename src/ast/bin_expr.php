<?php

namespace phs\ast;

class BinExpr extends Node
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
