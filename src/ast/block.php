<?php

namespace phs\ast;

class Block extends Node
{
  public $body;
  
  public function __construct($body)
  {
    $this->body = $body;
  }
}
