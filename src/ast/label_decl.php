<?php

namespace phs\ast;

class LabelDecl extends Node
{
  public $id;
  public $comp;
  
  public function __construct($id, $comp)
  {
    $this->id = $id;
    $this->comp = $comp;
  }
}
