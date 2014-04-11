<?php

namespace phs\front\ast;

class FinallyItem extends Node
{
  public $body;
  
  public function __construct($body)
  {
    $this->body = $body;
  }
}
