<?php

namespace phs\front\ast;

class PhpStmt extends Stmt
{
  public $usage;
  public $code;
  
  public function __construct($usage, $code)
  {
    $this->usage = $usage;
    $this->code = $code;
  }
}
