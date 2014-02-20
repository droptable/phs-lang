<?php

namespace phs\ast;

class VarItem extends Node
{
  public $dest;
  public $init;
  
  public function __construct($dest, $init)
  {
    $this->dest = $dest;
    $this->init = $init;
  }
}
