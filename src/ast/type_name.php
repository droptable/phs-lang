<?php

namespace phs\ast;

use phs\Location;

class TypeName extends Node
{
  // @var TypeId|Name
  public $name;
  
  // @var array
  public $gens;
  
  // @var array
  public $dims;
  
  /**
   * constructor
   *
   * @param Location        $loc
   * @param TypeId|Name     $name
   * @param array           $gens
   */
  public function __construct(Location $loc, $name, array $gens, array $dims)
  {
    parent::__construct($loc);
    
    assert($name instanceof Name ||
           $name instanceof TypeId);
    
    $this->name = $name;
    $this->gens = $gens;
    $this->dims = $dims;
  }
}
