<?php

namespace phs\ast;

class OffsetExpr extends Expr
{
  public $object;
  public $offset;
  
  public function __construct($object, $offset)
  {
    $this->object = $object;
    $this->offset = $offset;
  }

  public function __clone()
  {
    $this->object = clone $this->object;
    $this->offset = clone $this->offset;
    
    parent::__clone();
  }
}
