<?php

namespace phs\front\ast;

class CaseItem extends Node
{
  public $labels;
  public $body;
  
  public function __construct($labels, $body)
  {
    $this->labels = $labels;
    $this->body = $body;
  }
}
