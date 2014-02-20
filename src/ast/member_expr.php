<?php

namespace phs\ast;

class MemberExpr extends Node
{
  public $computed;
  public $obj;
  public $member;
  
  public function __construct($computed, $obj, $member)
  {
    $this->computed = $computed;
    $this->obj = $obj;
    $this->member = $member;
  }
}
