<?php

namespace phs\front\ast;

class GotoStmt extends Stmt
{
  public $id;
  
  public function __construct($id)
  {
    $this->id = $id;
  }
}