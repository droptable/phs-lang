<?php

namespace phs\ast;

class CaseItem extends Node
{
  public $label;
  public $body;
  
  public function __construct($label, $body)
  {
    $this->label = $label;
    $this->body = $body;
  }
}
