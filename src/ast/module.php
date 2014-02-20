<?php

namespace phs\ast;

class Module extends Node
{
  public $name;
  public $body;
  
  public function __construct($name, $body)
  {
    assert($body instanceof Module ||
           $body instanceof Program);
    
    $this->name = $name;
    $this->body = $body;
  }
}
