<?php

namespace phs\ast;

use phs\Location;

class CallExpr extends Expr
{
  // @var Expr  callee, well, that's it
  public $callee;
  
  // @var array<Expr>  arguments
  public $args;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Expr     $callee
   * @param array    $args
   */
  public function __construct(Location $loc, Expr $callee, array $args)
  {
    parent::__construct($loc);
    
    $this->callee = $callee;
    $this->args = $args;
  }
  
  public function __clone()
  {
    $this->callee = clone $this->callee;
    
    if ($this->args) {
      $args = $this->args;
      $this->args = [];
      
      foreach ($args as $arg)
        $this->args[] = clone $arg;
    }
    
    parent::__clone();
  }
}
