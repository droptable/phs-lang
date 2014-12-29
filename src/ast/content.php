<?php

namespace phs\ast;

use phs\Location;

class Content extends Node
{
  // @var array<Node>
  public $body;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param array    $body
   */
  public function __construct(Location $loc, array $body)
  {
    parent::__construct($loc);
    $this->body = $body;
  }

  public function __clone()
  {
    if ($this->body) {
      $body = $this->body;
      $this->body = [];
      
      foreach ($body as $node)
        $this->body[] = clone $node;
    }
    
    parent::__clone();
  }
}
