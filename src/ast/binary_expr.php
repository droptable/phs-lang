<?php

namespace phs\ast;

use phs\Location;

class BinaryExpr extends Expr
{
  // @var Expr  left-hand-side
  public $left;
  
  // @var int  operator
  public $op;
  
  // @var Expr  right-hand-side
  public $right;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Expr     $left
   * @param int      $op
   * @param Expr     $right
   */
  public function __construct(Location $loc, $op, Expr $left, Expr $right)
  {
    parent::__construct($loc);
    
    $this->left = $left;
    $this->op = $op;
    $this->right = $right;
  }
  
  public function __clone()
  {
    $this->left = clone $this->left;
    $this->right = clone $this->right;
    
    parent::__clone();
  }
}
