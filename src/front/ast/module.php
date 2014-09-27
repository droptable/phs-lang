<?php

namespace phs\front\ast;

class Module extends Node
{
  public $name;
  public $body;
  
  // @var ModuleScope 
  public $scope;
  
  public function __construct($name, $body)
  {
    assert($body === null ||
           $body instanceof Content);
    
    $this->name = $name;
    $this->body = $body;
  }

  public function __clone()
  {
    $this->name = clone $this->name;
    $this->body = clone $this->body;
    $this->scope = clone $this->scope;
    
    parent::__clone();
  }
}
