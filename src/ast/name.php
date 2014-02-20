<?php

namespace phs\ast;

class Name extends Node
{
  public $base;
  public $root;
  public $parts;
  
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
