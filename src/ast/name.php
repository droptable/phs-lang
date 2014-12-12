<?php

namespace phs\ast;

class Name extends Node
{
  public $base;
  public $root;
  public $type;
  public $self;
  public $parts;
  
  // @var Symbol
  public $symbol;
  
  public function __construct($base, $root, $type = null)
  {
    $this->base = $base;
    $this->root = $root;
    $this->type = $type;
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
