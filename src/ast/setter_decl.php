<?php

namespace phs\ast;

use phs\Location;

class SetterDecl extends Decl
{
  // @var Ident
  public $id;
  
  // @var array<Param>
  public $params;
  
  // @var Block|Expr
  public $body;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Ident    $id
   * @param array    $params
   * @param Node     $body
   */
  public function __construct(Location $loc, Ident $id, array $params, 
                              Node $body)
  {
    parent::__construct($loc);
    
    $this->id = $id;
    $this->params = $params;
    $this->body = $body;
  }

  public function __clone()
  {
    $this->id = clone $this->id;
    
    if ($this->params) {
      $params = $this->params;
      $this->params = [];
      
      foreach ($params as $param)
        $this->params[] = clone $param;      
    }
    
    $this->body = clone $this->body;
    
    parent::__clone();
  }
}
