<?php

namespace phs\ast;

use phs\Location;

class TryStmt extends Stmt
{
  // @var Block
  public $body;
  
  // @var array<CatchItem>
  public $catches;
  
  // @var array<FinallyItem>
  public $finalizer;
  
  /**
   * constructor
   *
   * @param Location         $loc
   * @param Block            $body
   * @param array|null       $catches
   * @param FinallyItem|null $finalizer
   */
  public function __construct(Location $loc, Block $body, array $catches = null, 
                              FinallyItem $finalizer = null)
  {
    parent::__construct($loc);
    
    $this->body = $body;
    $this->catches = $catches;
    $this->finalizer = $finalizer;
  }

  public function __clone()
  {
    $this->body = clone $this->body;
    
    if ($this->catches) {
      $catches = $this->catches;
      $this->catches = [];
      
      foreach ($catches as $catch)
        $this->catches[] = clone $catch;
    }
    
    $this->finalizer = clone $this->finalizer;
    
    parent::__clone();
  }
}
