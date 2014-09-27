<?php

namespace phs\front\ast;

class Unit extends Node
{
  public $body;
  
  // unit scope
  public $scope;
  
  public function __construct($body)
  {
    $this->body = $body;
  }

  public function __clone()
  {
    $this->body = clone $this->body;
    $this->scope = clone $this->scope;
    
    parent::__clone();
  }
}
