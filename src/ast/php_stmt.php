<?php

namespace phs\ast;

class PhpStmt extends Node
{
  public $usage;
  public $code;
  
  public function __construct($usage, $code)
  {
    $this->usage = $usage;
    $this->code = $code;
  }
}
