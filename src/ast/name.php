<?php

namespace phs\ast;

class Name extends Node
{
  public $base;
  public $root;
  public $parts;
  
  // symbol lookup cache
  public $symbol;
  
  public function __construct($base, $root)
  {
    $this->base = $base;
    $this->root = $root;
  }
  
  public function add($name)
  {
    $this->parts[] = $name;
  }
}
