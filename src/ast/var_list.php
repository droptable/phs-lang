<?php

namespace phs\ast;

use phs\Location;

class VarList extends Decl
{
  // @var array<Ident>
  public $vars;
  
  // @var Expr
  public $expr;
  
  /**
   * constructor
   *
   * @param Location      $loc
   * @param array<Ident>  $vars
   * @param Expr          $expr
   */
  public function __construct(Location $loc, array $vars, Expr $expr)
  {
    parent::__construct($loc);
    
    $this->vars = $vars;
    $this->expr = $expr;
  }

  public function __clone()
  {
    $vars = $this->vars;
    $this->vars = [];
    
    foreach ($vars as $var)
      $this->vars[] = clone $var;
    
    $this->expr = clone $expr;
    
    parent::__clone();    
  }
}
