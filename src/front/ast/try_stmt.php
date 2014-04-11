<?php

namespace phs\front\ast;

class TryStmt extends Stmt
{
  public $stmt;
  public $catches;
  public $finalizer;
  
  public function __construct($stmt, $catches, $finalizer)
  {
    $this->stmt = $stmt;
    $this->catches = $catches;
    $this->finalizer = $finalizer;
  }
}
