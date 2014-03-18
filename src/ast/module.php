<?php

namespace phs\ast;

class Module extends Node
{
  public $name;
  public $body;
  
  // scope
  public $scope;
  
  public function __construct($name, $body)
  {
    assert($body === null || is_array($body) ||
           $body instanceof Program);
    
    $this->name = $name;
    $this->body = $body;
  }
}
