<?php

namespace phs\ast;

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

  public function __clone()
  {
    $this->base = clone $this->base;
    
    if ($this->parts) {
      $parts = $this->parts;
      $this->parts = [];
      
      foreach ($parts as $part)
        $this->parts[] = clone $part;
    }
    
    if ($this->symbol)
      $this->symbol = clone $this->symbol;
    
    parent::__clone();
  }
}
