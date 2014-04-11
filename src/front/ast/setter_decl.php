<?php

namespace phs\front\ast;

class SetterDecl extends Decl
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
