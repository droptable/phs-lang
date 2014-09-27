<?php

namespace phs\front\ast;

class FinallyItem extends Node
{
  public $body;
  
  public function __construct($body)
  {
    $this->body = $body;
  }

  public function __clone()
  {
    $this->body = clone $this->body;
    
    parent::__clone();
  }
}
