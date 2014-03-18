<?php

namespace phs\ast;

class ContinueStmt extends Stmt
{
  public $id;
  
  public function __construct($id)
  {
    $this->id = $id;
  }
}
