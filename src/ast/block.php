<?php

namespace phs\ast;

use phs\Scope;
use phs\Location;

class Block extends Stmt
{
  // @var array  inner statements
  public $body;
  
  // @var Scope  block-scope
  public $scope;
  
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
    
    if ($this->scope)
      $this->scope = clone $this->scope;
    
    parent::__clone();
  }
}
