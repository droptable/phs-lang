<?php

namespace phs\ast;

class IfStmt extends Stmt
{
  public $test;
  public $stmt;
  public $elsifs;
  public $els;
  
  public function __construct($test, $stmt, $elsifs, $els)
  {
    $this->test = $test;
    $this->stmt = $stmt;
    $this->elsifs = $elsifs;
    $this->els = $els;
  }

  public function __clone()
  {
    $this->test = clone $this->test;
    $this->stmt = clone $this->stmt;
    
    if ($this->elsifs) {
      $elsifs = $this->elsifs;
      $this->elsifs = [];
      
      foreach ($elsifs as $elsif)
        $this->elsifs[] = clone $elsif;
    }
    
    if ($this->els)
      $this->els = clone $this->els;
    
    parent::__clone();
  }
}
