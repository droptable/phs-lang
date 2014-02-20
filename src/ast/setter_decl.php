<?php

namespace phs\ast;

class SetterDecl extends Node
{
  public $id;
  public $params;
  public $body;
  
  public function __construct($id, $params, $body)
  {
    $this->id = $id;
    $this->params = $params;
    $this->body = $body;
  }
}
