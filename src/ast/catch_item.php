<?php

namespace phs\ast;

class CatchItem extends Node
{
  public $name;
  public $id;
  public $body;
  
  public function __construct($name, $id, $body)
  {
    $this->name = $name;
    $this->id = $id;
    $this->body = $body;
  }
}
