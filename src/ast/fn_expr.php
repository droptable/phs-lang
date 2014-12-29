<?php

namespace phs\ast;

use phs\Location;

class FnExpr extends Expr
{
  // @var Ident
  public $id;
  
  // @var array<ParamDecl>
  public $params;
  
  // @var TypeName
  public $hint;
  
  // @var Block|Expr
  public $body;
  
  // @var Scope
  public $scope;
  
  // @var Symbol
  public $sym;
    
  /**
   * constructor
   *
   * @param Location      $loc
   * @param Ident|null    $id
   * @param array         $params
   * @param TypeName|null $hint
   * @param Node          $body
   */
  public function __construct(Location $loc, Ident $id = null, array $params, 
                              TypeName $hint = null, Node $body)
  {
    parent::__construct($loc);
    
    $this->id = $id;
    $this->params = $params;
    $this->hint = $hint;
    $this->body = $body;
  }

  public function __clone()
  {
    if ($this->id)
      $this->id = clone $this->id;
    
    $this->body = clone $this->body;
    
    if ($this->params) {
      $params = $this->params;
      $this->params = [];
      
      foreach ($params as $param)
        $this->params[] = clone $param;      
    }
    
    if ($this->scope) 
      $this->scope = clone $this->scope;
    
    // not done here
    $this->symbol = null;
    
    parent::__clone();
  }
}
