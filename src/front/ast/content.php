<?php

namespace phs\front\ast;

class Content extends Node
{
  public $uses;
  public $body;
  
  public function __construct($uses, $body)
  {
    $this->uses = $uses;
    $this->body = $body;
  }
}
