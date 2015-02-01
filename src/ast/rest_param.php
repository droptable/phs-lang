<?php

namespace phs\ast;

use phs\Symbol;
use phs\Location;

class RestParam extends Node
{
  // @var Ident
  public $id;
  
  // @var boolean  pass-by-ref
  public $ref;
  
  // @var TypeName
  public $hint;
  
  // @var Symbol
  public $symbol;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param bool     $ref
   * @param Ident    $id
   * @param TypeName $hint
   */
  public function __construct(Location $loc, $ref, Ident $id, TypeName $hint)
  {
    parent::__construct($loc);
    
    $this->hint = $hint;
    $this->id = $id;
    $this->ref = $ref;
  }

  public function __clone()
  {
    $this->id = clone $this->id;
    
    if ($this->hint)
      $this->hint = clone $this->hint;
    
    $this->symbol = null;
    
    parent::__clone();
  }
}
