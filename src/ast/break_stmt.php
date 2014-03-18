<?php

namespace phs\ast;

class BreakStmt extends Stmt
{
  public $id;
  
  public function __construct($id)
  {
    $this->id = $id;
  }
}
