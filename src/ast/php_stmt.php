<?php

namespace phs\ast;

class PhpStmt extends Stmt
{
  public $usage;
  public $code;
  
  public function __construct($usage, $code)
  {
    $this->usage = $usage;
    $this->code = $code;
  }

  public function __clone()
  {
    if ($this->usage) {
      $usage = $this->usage;
      $this->usage = [];
      
      foreach ($usage as $use)
        $this->usage[] = clone $use;
    }
    
    $this->code = clone $this->code;
    
    parent::__clone();
  }
}
