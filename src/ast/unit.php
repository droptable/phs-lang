<?php

namespace phs\ast;

class Unit extends Node
{
  public $body;
  public $dest; // gets set later
  
  public function __construct($body)
  {
    $this->body = $body;
  }
}
