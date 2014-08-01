<?php

namespace phs\front\ast;

class TryStmt extends Stmt
{
  public $body;
  public $catches;
  public $finalizer;
  
  public function __construct($body, $catches, $finalizer)
  {
    $this->body = $body;
    $this->catches = $catches;
    $this->finalizer = $finalizer;
  }
}
