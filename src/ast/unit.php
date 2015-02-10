<?php

namespace phs\ast;

use phs\Location;

class Unit extends Node
{
  // @var Module|array<Decl>
  public $body;
  
  // unit scope
  public $scope;
  
  /**
   * constructor
   *
   * @param Location                $loc
   * @param Module|array<Decl>  $body
   */
  public function __construct(Location $loc, $body)
  {
    parent::__construct($loc);
    
    assert(is_array($body) || $body instanceof Module);
    $this->body = $body;
  }

  public function __clone()
  {
    $this->body = clone $this->body;
    $this->scope = clone $this->scope;
    
    parent::__clone();
  }
}
