<?php

namespace phs\ast;

use phs\Symbol;
use phs\Location;

class MemberExpr extends Expr
{
  // @var Expr
  public $object;
  
  // @var Ident|Expr
  public $member;
  
  // @var bool
  public $computed;
  
  // @var Symbol
  public $sym;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Expr     $object
   * @param Node     $member
   * @param boolean  $computed
   */
  public function __construct(Location $loc, Expr $object,  
                              Node $member, $computed = false)
  {
    parent::__construct($loc);
    
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
