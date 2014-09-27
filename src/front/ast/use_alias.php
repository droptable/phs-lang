<?php

namespace phs\front\ast;

class UseAlias extends Node
{
  public $name;
  public $alias;
  
  public function __construct($name, $alias)
  {
    $this->name = $name;
    $this->alias = $alias;
  }

  public function __clone()
  {
    $this->name = clone $this->name;
    $this->alias = clone $this->alias;
    
    parent::__clone();
  }
}
