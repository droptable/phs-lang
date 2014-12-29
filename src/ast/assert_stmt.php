<?php

namespace phs\ast;

use phs\Location;

class AssertStmt extends Stmt
{
  // @var Expr  test-expression
  public $expr;
  
  // @var StrLit  debug message
  public $message;
  
  /**
   * constructor
   *
   * @param Location    $loc
   * @param Expr        $expr
   * @param StrLit|null $message
   */
  public function __construct(Location $loc, Expr $expr, StrLit $message = null)
  {
    parent::__construct($loc);
    
    $this->expr = $expr;
    $this->message = $message;
  }
  
  public function __clone()
  {
    $this->expr = clone $this->expr;
    
    if ($this->message) 
      $this->message = clone $this->message;
    
    parent::__clone();
  }
}
