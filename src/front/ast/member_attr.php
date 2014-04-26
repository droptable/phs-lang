<?php

namespace phs\front\ast;

class MemberAttr extends Node 
{
  public $attr;
  public $member;
  
  public function __construct($attr, $member)
  {
    $this->attr = $attr;
    $this->member = $member;
  }
}
