<?php

namespace phs\front\ast;

class MemberExpr extends Expr
{
  public $object;
  public $member;
  public $computed;
  
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
    
    parent::__clone();
  }
}
