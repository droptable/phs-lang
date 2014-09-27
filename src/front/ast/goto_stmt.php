<?php

namespace phs\front\ast;

class GotoStmt extends Stmt
{
  public $id;
  
  public function __construct($id)
  {
    $this->id = $id;
  }

  public function __clone()
  {
    $this->id = clone $this->id;
    
    parent::__clone();
  }
}
