<?php

namespace phs\front\ast;

class LabelDecl extends Decl
{
  public $id;
  public $stmt;
  
  public function __construct($id, $stmt)
  {
    $this->id = $id;
    $this->stmt = $stmt;
  }

  public function __clone()
  {
    $this->id = clone $this->id;
    $this->stmt = clone $this->stmt;
    
    parent::__clone();
  }
}
