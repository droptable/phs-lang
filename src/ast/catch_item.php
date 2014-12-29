<?php

namespace phs\ast;

use phs\Location;

class CatchItem extends Node
{
  // @var TypeName
  public $type;
  
  // @var Ident
  public $id;
  
  // @var Block
  public $body;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param TypeName $name
   * @param Ident    $id
   * @param Block    $body
   */
  public function __construct(Location $loc, TypeName $type, Ident $id, Block $body)
  {
    parent::__construct($loc);
    
    $this->type = $type;
    $this->id = $id;
    $this->body = $body;
  }

  public function __clone()
  {
    $this->name = clone $this->name;
    $this->id = clone $this->id;
    $this->body = clone $this->body;
    
    parent::__clone();
  }
}
