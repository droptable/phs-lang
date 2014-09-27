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

  public function __clone()
  {
    $this->body = clone $this->body;
    
    if ($this->catches) {
      $catches = $this->catches;
      $this->catches = [];
      
      foreach ($catches as $catch)
        $this->catches[] = clone $catch;
    }
    
    $this->finalizer = clone $this->finalizer;
    
    parent::__clone();
  }
}
