<?php

namespace phs\ast;

class Block extends Node
{
  public $body;
  
  // gets filled-in by the analyzer
  public $scope;
  
  public function __construct($body)
  {
    $this->body = $body;
  }
}
