<?php

namespace phs\front\ast;

class TestStmt extends Stmt
{
  public $name;
  public $block;
  
  public function __construct($name, $block)
  {
    $this->name = $name;
    $this->block = $block;
  }

  public function __clone()
  {
    if ($this->name)
      $this->name = clone $this->name;
    
    $this->block = clone $this->block;
    
    parent::__clone();
  }
}
