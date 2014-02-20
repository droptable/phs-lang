<?php

namespace phs\ast;

class Program extends Node
{
  public $body;
  
  public function __construct($body)
  {
    $this->body = $body;
  }
}
