<?php

namespace phs\ast;

class ObjLit extends Expr
{
  public $pairs;
  
  public function __construct($pairs)
  {
    $this->pairs = $pairs;
  }

  public function __clone()
  {
    if ($this->pairs) {
      $pairs = $this->pairs;
      $this->pairs = [];
      
      foreach ($pairs as $pair)
        $this->pairs[] = clone $pair;
    }
    
    parent::__clone();
  }
}
