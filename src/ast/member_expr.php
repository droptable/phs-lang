<?php

namespace phs\ast;

class MemberExpr extends Expr
{
  public $object;
  public $member;
  public $computed;
  
  // @var Symbol
  public $symbol;
  
  public function __construct($object, $member, $computed = false)
  {
    $this->object = $object;
    $this->member = $member;
    $this->computed = $computed;
  }

  public function __clone()
  {
    $this->object = clone $this->object;
    $this->member = clone $this->member;
    
    $this->symbol = null;
    
    parent::__clone();
  }
}
