<?php

namespace phs\ast;

class ExportItem extends Node
{
  public $id;
  public $alias;
  
  public function __construct($id, $alias)
  {
    $this->id = $id;
    $this->alias = $alias;
  }
}