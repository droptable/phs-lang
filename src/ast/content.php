<?php

namespace phs\ast;

class Content extends Node
{
  public $body;
  
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
    
    parent::__clone();
  }
}
