<?php

namespace phs\ast;

class ForInStmt extends Node
{
  public $lhs;
  public $rhs;
  public $stmt;
  
  public function __construct($lhs, $rhs, $stmt)
  {
    $this->lhs = $lhs;
    $this->rhs = $rhs;
    $this->stmt = $stmt;
  }
}
