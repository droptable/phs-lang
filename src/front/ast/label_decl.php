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
}
