<?php

namespace phs\ast;

use phs\Location;

class VarItem extends Node
{
  // @var Ident
  public $id;
  
  // @var Expr
  public $init;
  
  // @var TypeName
  public $type;
  
  // @var bool
  public $ref;
  
  // @var Symbol
  public $symbol;
  
  /**
   * constructor
   *
   * @param Location      $loc 
   * @param Ident         $id
   * @param TypeName|null $type
   * @param Expr          $init
   * @param bool          $ref
   */
  public function __construct(Location $loc, Ident $id, 
                              TypeName $type = null, Expr $init, $ref)
  {
    parent::__construct($loc);
    
    $this->id = $id;
    $this->type = $type;
    $this->init = $init;
    $this->ref = $ref;
  }

  public function __clone()
  {
    $this->id = clone $this->id;
    
    if ($this->init)
      $this->init = clone $this->init;
    
    parent::__clone();
  }
}
