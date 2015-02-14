<?php

namespace phs\ast;

use phs\Location;

class CheckExpr extends Expr
{
  // @var Expr
  public $left;
  
  // @var int  is or !is
  public $op;
  
  // @var TypeName
  public $right;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Expr     $left
   * @param int      $op
   * @param TypeName $right
   */
  public function __construct(Location $loc, $op, Expr $left, TypeName $right)
  {
    parent::__construct($loc);
    
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
