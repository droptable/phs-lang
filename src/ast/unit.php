<?php

namespace phs\ast;

class Unit extends Node
{
  public $body;
  
  public function __construct($body)
  {
    $this->body = $body;
  }
}
