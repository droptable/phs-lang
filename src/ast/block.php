<?php

namespace phs\ast;

class Block extends Stmt
{
  public $body;
  
  // gets filled-in by the analyzer
  public $scope;
  
  public function __construct($body)
  {
    $this->body = $body;
  }
}
