<?php

namespace phs\front\ast;

class Block extends Stmt
{
  public $body;
  
  // not bound to a function
  public $solitary = true;
  
  // gets filled-in by the analyzer
  public $scope;
  
  public function __construct($body)
  {
    $this->body = $body;
  }
}
