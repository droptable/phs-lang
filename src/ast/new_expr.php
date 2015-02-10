<?php

namespace phs\ast;

use phs\Location;

class NewExpr extends Expr
{
  // @var TypeName
  public $type;
  
  // @var array<Expr|NamedArg>
  public $args;
  
  /**
   * construct
   *
   * @param Location $loc
   * @param TypeName $type
   * @param array    $args
   */
  public function __construct(Location $loc, 
                              TypeName $type = null, 
                              array $args = null)
  {
    parent::__construct($loc);
    
    $this->type = $type;
    $this->args = $args;
  }

  public function __clone()
  {
    $this->name = clone $this->name;
    
    if ($this->args) {
      $args = $this->args;
      $this->args = [];
      
      foreach ($args as $arg)
        $this->args[] = clone $arg;
    }
    
    parent::__clone();
  }
}
