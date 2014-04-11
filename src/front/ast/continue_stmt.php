<?php

namespace phs\front\ast;

class ContinueStmt extends Stmt
{
  public $id;
  
  public function __construct($id)
  {
    $this->id = $id;
  }
}
