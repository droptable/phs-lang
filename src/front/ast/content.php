<?php

namespace phs\front\ast;

class Content extends Node
{
  public $body;
  
  public function __construct($body)
  {
    $this->body = $body;
  }
}
