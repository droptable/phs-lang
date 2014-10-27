<?php

namespace phs\ast;

class AliasDecl extends Decl
{
  public $id;
  public $orig;
  
  public function __construct($id, $orig)
  {
    $this->id = $id;
    $this->orig = $orig;
  }
  
  public function __clone()
  {
    $this->id = clone $this->id;
    $this->orig = clone $this->orig;
    
    parent::__clone();
  }
}
