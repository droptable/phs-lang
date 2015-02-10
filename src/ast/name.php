<?php

namespace phs\ast;

use phs\Symbol;
use phs\Location;

class Name extends Expr
{
  // @var Ident
  public $base;
  
  // @var bool
  public $root;
  
  // @var bool
  public $self;
  
  // @var array<Ident>
  public $parts;
  
  // @var Symbol
  public $sym;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Ident    $base
   * @param bool     $root
   * @param bool     $self
   */
  public function __construct(Location $loc, Ident $base, $root, $self)
  {
    parent::__construct($loc);

    $this->base = $base;
    $this->root = $root;
    $this->self = $self;
  }
  
  /**
   * add a part
   *
   * @param Ident $id
   */
  public function add(Ident $id)
  {
    $this->parts[] = $id;
  }

  public function __clone()
  {
    $this->base = clone $this->base;
    
    if ($this->parts) {
      $parts = $this->parts;
      $this->parts = [];
      
      foreach ($parts as $part)
        $this->parts[] = clone $part;
    }
    
    if ($this->symbol)
      $this->symbol = clone $this->symbol;
    
    parent::__clone();
  }
}
