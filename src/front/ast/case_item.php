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
  
  public function __clone()
  {
    $labels = $this->labels;
    $this->labels = [];
    
    foreach ($labels as $label)
      $this->labels[] = clone $label;    
    
    $body = $this->body;
    $this->body = [];
    
    foreach ($body as $node)
      $this->body[] = clone $node;
    
    parent::__clone();
  }
}
