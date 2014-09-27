<?php

namespace phs\front\ast;

class ContinueStmt extends Stmt
{
  public $id;
  
  public function __construct($id)
  {
    $this->id = $id;
  }

  public function __clone()
  {
    if ($this->id)
      $this->id = clone $this->id;
    
    parent::__clone();
  }
}
