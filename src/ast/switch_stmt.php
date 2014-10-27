<?php

namespace phs\ast;

class SwitchStmt extends Stmt
{
  public $test;
  public $cases;
  
  public function __construct($test, $cases)
  {
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
