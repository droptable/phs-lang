<?php

namespace phs\ast;

use phs\Location;

class CaseItem extends Node
{
  // @var array<CaseLabel>  labels
  public $labels;
  
  // @var array<Stmt>  inner statements
  public $body;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param array    $labels
   * @param array    $body
   */
  public function __construct(Location $loc, array $labels, array $body)
  {
    parent::__construct($loc);
    
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
