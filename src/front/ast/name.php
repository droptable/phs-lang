<?php

namespace phs\front\ast;

class Name extends Node
{
  public $base;
  public $root;
  public $parts;
  
  // @var Symbol
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
