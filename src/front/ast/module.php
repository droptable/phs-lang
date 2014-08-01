<?php

namespace phs\front\ast;

class Module extends Node
{
  public $name;
  public $body;
  
  // @var phs\front\ModuleScope
  // this is a workaround 
  public $scope;
  
  public function __construct($name, $body)
  {
    assert($body === null ||
           $body instanceof Content);
    
    $this->name = $name;
    $this->body = $body;
  }
}
