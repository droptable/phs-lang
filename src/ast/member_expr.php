<?php

namespace phs\ast;

class MemberExpr extends Node
{
  public $prop;
  public $computed;
  public $obj;
  public $member;
  
  public function __construct($prop, $computed, $obj, $member)
  {
    $this->prop = $prop; // true: object, false: array
    $this->computed = $computed;
    $this->obj = $obj;
    $this->member = $member;
  }
}
