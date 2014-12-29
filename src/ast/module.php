<?php

namespace phs\ast;

use phs\Scope;
use phs\ModuleScope;
use phs\Location;

class Module extends Node
{
  // @var Name
  public $name;
  
  // @var Content
  public $body;
  
  // @var ModuleScope 
  public $scope;
  
  /**
   * constructor
   *
   * @param Location     $loc
   * @param Name         $name
   * @param Content|null $body
   */
  public function __construct(Location $loc, Name $name, Content $body = null)
  {
    parent::__construct($loc);
    
    $this->name = $name;
    $this->body = $body;
  }

  public function __clone()
  {
    $this->name = clone $this->name;
    $this->body = clone $this->body;
    $this->scope = clone $this->scope;
    
    parent::__clone();
  }
}
