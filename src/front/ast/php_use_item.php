<?php

namespace phs\front\ast;

class PhpUseItem extends Node
{
  public $id;
  public $alias;
  
  public function __construct($id, $alias)
  {
    $this->id = $id;
    $this->alias = $alias;
  }
}
