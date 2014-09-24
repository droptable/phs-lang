<?php

namespace phs\front\ast;

class Name extends Node
{
  public $base;
  public $root;
  public $self;
  public $parts;
  
  // @var Symbol
  public $symbol;
  
  public function __construct($base, $root, $self)
  {
    $this->base = $base;
    $this->root = $root;
    $this->self = $self;
  }
  
  public function add($name)
  {
    $this->parts[] = $name;
  }
}
