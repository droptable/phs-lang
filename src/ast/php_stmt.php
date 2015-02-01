<?php

namespace phs\ast;

// unused in the parser v2
use phs\Location;

class PhpStmt extends Stmt
{
  // @var array<PhpUse>
  public $usage;
  
  // @var StrLit
  public $code;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param array    $usage
   * @param StrLit   $code
   */
  public function __construct(Location $loc, array $usage, StrLit $code)
  {
    parent::__construct($loc);
    
    $this->usage = $usage;
    $this->code = $code;
  }

  public function __clone()
  {
    if ($this->usage) {
      $usage = $this->usage;
      $this->usage = [];
      
      foreach ($usage as $use)
        $this->usage[] = clone $use;
    }
    
    $this->code = clone $this->code;
    
    parent::__clone();
  }
}
