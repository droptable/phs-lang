<?php

namespace phs\ast;

class GetterDecl extends Decl
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
