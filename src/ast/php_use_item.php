<?php

namespace phs\ast;

class PhpUseItem extends Node
{
  public $id;
  public $alias;
  
  public function __construct($id, $alias)
  {
    $this->id = $id;
    $this->alias = $alias;
  }

  public function __clone()
  {
    $this->id = clone $this->id;
    
    if ($this->alias)
      $this->alias = clone $this->alias;
    
    parent::__clone();
  }
}
