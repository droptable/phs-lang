<?php

namespace phs\ast;

class VarItem extends Node
{
  public $dest;
  public $init;
  public $ref;
  
  public function __construct($dest, $init, $ref)
  {
    $this->dest = $dest;
    $this->init = $init;
    $this->ref = $ref;
  }
}
