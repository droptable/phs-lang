<?php

namespace phs\front\ast;

class Module extends Node
{
  public $name;
  public $body;
  
  // scope
  public $scope;
  
  // module
  public $module;
  
  public function __construct($name, $body)
  {
    assert($body === null ||
           $body instanceof Content);
    
    $this->name = $name;
    $this->body = $body;
  }
}
