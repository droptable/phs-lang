<?php

namespace phs\front\ast;

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

  public function __clone()
  {
    $this->name = clone $this->name;
    $this->id = clone $this->id;
    $this->body = clone $this->body;
    
    parent::__clone();
  }
}
