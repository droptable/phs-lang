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
}
