<?php

namespace phs\ast;

class FinallyItem extends Node
{
  public $body;
  
  public function __construct($body)
  {
    $this->body = $body;
  }
}
