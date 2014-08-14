<?php

namespace phs\front\ast;

class OffsetExpr extends Expr
{
  public $object;
  public $offset;
  
  public function __construct($object, $offset)
  {
    $this->object = $object;
    $this->offset = $offset;
  }
}
