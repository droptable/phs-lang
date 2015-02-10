<?php

namespace phs\ast;

use phs\Location;

class SwitchStmt extends Stmt
{
  // @var Expr
  public $test;
  
  // @var array<CaseItem>
  public $cases;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Expr     $test
   * @param array    $cases
   */
  public function __construct(Location $loc, Expr $test, array $cases)
  {
    parent::__construct($loc);
    
    $this->test = $test;
    $this->cases = $cases;
  }

  public function __clone()
  {
    $this->test = clone $this->test;
    
    if ($this->cases) {
      $cases = $this->cases;
      $this->cases = [];
      
      foreach ($cases as $case)
        $this->cases[] = clone $case;
    }
    
    parent::__clone();
  }
}
