<?php

namespace phs\ast;

class VarList extends Decl
{
  public $vars;
  public $expr;
  
  public function __construct($vars, $expr)
  {
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
