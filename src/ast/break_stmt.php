<?php

namespace phs\ast;

class BreakStmt extends Stmt
{
  public $id;
  
  // @var int  level to break (gets resolved later)
  public $level;
  
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
