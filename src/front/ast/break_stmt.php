<?php

namespace phs\front\ast;

class BreakStmt extends Stmt
{
  public $id;
  
  public function __construct($id)
  {
    $this->id = $id;
  }
}
