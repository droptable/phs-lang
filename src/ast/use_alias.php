<?php

namespace phs\ast;

class UseAlias extends Node
{
  public $name;
  public $alias;
  
  public function __construct($name, $alias)
  {
    $this->name = $name;
    $this->alias = $alias;
  }
}
