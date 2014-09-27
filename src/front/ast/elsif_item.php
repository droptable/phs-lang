<?php

namespace phs\front\ast;

class ElsifItem extends Node
{
  public $test;
  public $stmt;
  
  public function __construct($test, $stmt)
  {
    $this->test = $test;
    $this->stmt = $stmt;
  }

  public function __clone()
  {
    $this->test = clone $this->test;
    $this->stmt = clone $this->stmt;
    
    parent::__clone();
  }
}
