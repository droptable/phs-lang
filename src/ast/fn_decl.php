<?php

namespace phs\ast;

use phs\Scope;
use phs\FnScope;
use phs\Location;

class FnDecl extends Decl
{
  // @var array<Token>  modifiers
  public $mods;
  
  // @var Ident
  public $id;
  
  // @var array<ParamDecl>
  public $params;
  
  // @var TypeName
  public $hint;
  
  // @var Block|Expr
  public $body;
    
  // @var FnScope
  public $scope;
  
  /**
   * constructor
   *
   * @param Location       $loc
   * @param array          $mods
   * @param Ident          $id
   * @param array          $params
   * @param TypeName|null  $hint
   * @param Node|null      $body
   */
  public function __construct(Location $loc, array $mods, Ident $id, 
                              array $params, TypeName $hint = null, 
                              Node $body = null)
  {
    parent::__construct($loc);
    
    $this->mods = $mods;
    $this->id = $id;
    $this->params = $params;
    $this->hint = $hint;
    $this->body = $body;
  }

  public function __clone()
  {
    if ($this->mods) {
      $mods = $this->mods;
      $this->mods = [];
      
      foreach ($mods as $mod)
        $this->mods[] = clone $mod;  
    }
    
    $this->id = clone $this->id;
    
    if ($this->params) {
      $params = $this->params;
      $this->params = [];
      
      foreach ($params as $param)
        $this->params[] = clone $param;      
    }
    
    if ($this->body)
      $this->body = clone $this->body;
    
    if ($this->scope)
      $this->scope = clone $this->scope;
    
    parent::__clone();
  }
}
