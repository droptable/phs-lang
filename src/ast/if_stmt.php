<?php

namespace phs\ast;

use phs\Location;

class IfStmt extends Stmt
{
  // @var Expr  condition
  public $test;
  
  // @var Stmt  inner statements
  public $stmt;
  
  // @var array<ElifItem>
  public $elifs;
  
  // @var ElseItem
  public $altn;
  
  /**
   * constructor
   *
   * @param Location      $loc
   * @param Expr          $test
   * @param Stmt          $stmt
   * @param array|null    $elifs
   * @param ElseItem|null $altn
   */
  public function __construct(Location $loc, Expr $test, Stmt $stmt, 
                              array $elifs = null, ElseItem $altn = null)
  {
    parent::__construct($loc);
    
    $this->test = $test;
    $this->stmt = $stmt;
    $this->elifs = $elifs;
    $this->altn = $altn;
  }

  public function __clone()
  {
    $this->test = clone $this->test;
    $this->stmt = clone $this->stmt;
    
    if ($this->elsifs) {
      $elsifs = $this->elsifs;
      $this->elsifs = [];
      
      foreach ($elsifs as $elsif)
        $this->elsifs[] = clone $elsif;
    }
    
    if ($this->els)
      $this->els = clone $this->els;
    
    parent::__clone();
  }
}
