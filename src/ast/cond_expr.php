<?php

namespace phs\ast;

use phs\Location;

class CondExpr extends Expr
{
  // @var Expr  condition
  public $test;
  
  // @var Expr  consequent
  public $then;
  
  // @var Expr  alternate
  public $altn;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Expr     $test
   * @param Expr     $then
   * @param Expr     $altn
   */
  public function __construct(Location $loc, Expr $test, Expr $then, Expr $altn)
  {
    parent::__construct($loc);
    
    $this->test = $test;
    $this->then = $then;
    $this->altn = $altn;
  }

  public function __clone()
  {
    $this->test = clone $this->test;
    
    if ($this->then)
      $this->then = clone $this->then;
    
    $this->els = clone $this->els;
  
    parent::__clone();
  }
}
