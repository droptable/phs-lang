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
  
  public function __clone()
  {
    if ($this->body) {
      $body = $this->body;
      $this->body = [];
      
      foreach ($body as $node)
        $this->body[] = clone $node;
    }
    
    if ($this->scope)
      $this->scope = clone $this->scope;
    
    parent::__clone();
  }
}
