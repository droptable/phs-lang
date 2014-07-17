<?php

namespace phs\front\ast;

class MemberExpr extends Expr
{
  public $prop;
  public $computed;
  public $obj;
  public $member;
  
  public $variant = 'generic';
  
  public function __construct($prop, $computed, $obj, $member)
  {
    $this->prop = $prop; // true: object, false: array
    $this->computed = $computed;
    $this->obj = $obj;
    $this->member = $member;
  }
}