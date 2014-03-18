<?php

namespace phs\ast;

class TestStmt extends Stmt
{
  public $name;
  public $block;
  
  public function __construct($name, $block)
  {
    $this->name = $name;
    $this->block = $block;
  }
}
